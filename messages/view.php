<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Require login
requireLogin();

// Get message ID
$message_id = intval($_GET['id'] ?? 0);
if ($message_id <= 0) {
    redirect('index.php?error=Invalid message ID');
}

// Get message details
$sql = "SELECT m.*, 
        s.name as sender_name, s.role as sender_role,
        r.name as recipient_name, r.role as recipient_role
        FROM messages m 
        JOIN users s ON m.sender_id = s.id 
        JOIN users r ON m.receiver_id = r.id 
        WHERE m.id = ?";
$stmt = prepare($sql);
$stmt->bind_param("i", $message_id);
$stmt->execute();
$message = $stmt->get_result()->fetch_assoc();

if (!$message) {
    redirect('index.php?error=Message not found');
}

// Check if user has permission to view this message
$current_user_id = $_SESSION['user_id'];
if ($message['sender_id'] !== $current_user_id && $message['receiver_id'] !== $current_user_id) {
    redirect('index.php?error=Access denied');
}

$page_title = "Message - Smart Hospital Management System";
$page_heading = "Message Details";

// Mark as read if recipient
        if ($message['receiver_id'] === $current_user_id && $message['read'] == 0) {
            $update_sql = "UPDATE messages SET `read` = 1, read_at = NOW() WHERE id = ?";
            $update_stmt = prepare($update_sql);
            $update_stmt->bind_param("i", $message_id);
            $update_stmt->execute();
        }

include '../includes/header.php';
?>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Message Details</h5>
            </div>
            <div class="card-body">
                <div class="mb-4">
                    <h4 class="mb-2"><?php echo htmlspecialchars($message['subject']); ?></h4>
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="badge <?php echo $message['read'] ? 'bg-success' : 'bg-primary'; ?>">
                    <?php echo $message['read'] ? 'Read' : 'Unread'; ?>
                </span>
                        </div>
                        <small class="text-muted"><?php echo formatDateTime($message['created_at']); ?></small>
                    </div>
                </div>
                
                <hr>
                
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h6 class="text-primary">From:</h6>
                        <p><?php echo htmlspecialchars($message['sender_name']); ?> (<?php echo ucfirst($message['sender_role']); ?>)</p>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-primary">To:</h6>
                        <p><?php echo htmlspecialchars($message['recipient_name']); ?> (<?php echo ucfirst($message['recipient_role']); ?>)</p>
                    </div>
                </div>
                
                <div class="mb-4">
                    <h6 class="text-primary">Message:</h6>
                    <div class="p-3 bg-light rounded">
                        <?php echo nl2br(htmlspecialchars($message['message'])); ?>
                    </div>
                </div>
                
                <?php if ($message['read_at']): ?>
                    <div class="mb-4">
                        <h6 class="text-primary">Read at:</h6>
                        <p><?php echo formatDateTime($message['read_at']); ?></p>
                    </div>
                <?php endif; ?>
                
                <div class="d-flex justify-content-between">
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Messages
                    </a>
                    <?php if ($message['recipient_id'] === $current_user_id): ?>
                        <a href="compose.php?recipient_id=<?php echo $message['sender_id']; ?>&subject=Re: <?php echo htmlspecialchars($message['subject']); ?>" class="btn btn-primary">
                            <i class="fas fa-reply me-2"></i>Reply
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h6 class="card-title mb-0">Message Info</h6>
            </div>
            <div class="card-body">
                <div class="small">
                    <div class="mb-2">
                        <strong>Read:</strong> <?php echo $message['read'] ? 'Yes' : 'No'; ?>
                    </div>
                    <?php if ($message['read_at']): ?>
                        <div class="mb-2"><strong>Read:</strong> <?php echo formatDateTime($message['read_at']); ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
