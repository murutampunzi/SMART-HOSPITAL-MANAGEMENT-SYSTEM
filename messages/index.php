<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Require login
requireLogin();

$page_title = "Messages - Smart Hospital Management System";
$page_heading = "Messages";

// Get user role and ID
$user_role = $_SESSION['user_role'];
$user_id = $_SESSION['user_id'];

// Handle search and filters
$search = sanitizeInput($_GET['search'] ?? '');
$folder = sanitizeInput($_GET['folder'] ?? 'inbox');
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * ITEMS_PER_PAGE;

// Build query based on folder
$where_conditions = [];
$params = [];
$types = '';

if ($folder === 'inbox') {
    $where_conditions[] = "m.receiver_id = ? AND m.deleted_by_receiver = 0";
    $params[] = $user_id;
    $types .= 'i';
} elseif ($folder === 'sent') {
    $where_conditions[] = "m.sender_id = ? AND m.deleted_by_sender = 0";
    $params[] = $user_id;
    $types .= 'i';
} elseif ($folder === 'trash') {
    $where_conditions[] = "(m.sender_id = ? AND m.deleted_by_sender = 1) OR (m.receiver_id = ? AND m.deleted_by_receiver = 1)";
    $params = array_merge($params, [$user_id, $user_id]);
    $types .= 'ii';
}

if (!empty($search)) {
    $where_conditions[] = "(m.subject LIKE ? OR m.message LIKE ? OR u_sender.name LIKE ? OR u_receiver.name LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
    $types .= str_repeat('s', 4);
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Get total count
$count_sql = "SELECT COUNT(*) as total 
             FROM messages m 
             JOIN users u_sender ON m.sender_id = u_sender.id 
             JOIN users u_receiver ON m.receiver_id = u_receiver.id 
             $where_clause";
$count_stmt = prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_messages = $count_stmt->get_result()->fetch_assoc()['total'];

// Get pagination
$pagination = getPagination($total_messages, ITEMS_PER_PAGE, $page);

// Get messages
$sql = "SELECT m.*, 
              u_sender.name as sender_name, u_sender.role as sender_role,
              u_receiver.name as receiver_name, u_receiver.role as receiver_role
       FROM messages m 
       JOIN users u_sender ON m.sender_id = u_sender.id 
       JOIN users u_receiver ON m.receiver_id = u_receiver.id 
       $where_clause 
       ORDER BY m.created_at DESC 
       LIMIT ? OFFSET ?";
$stmt = prepare($sql);
$all_params = array_merge($params, [$pagination['items_per_page'], $pagination['offset']]);
$all_types = $types . 'ii';
$stmt->bind_param($all_types, ...$all_params);
$stmt->execute();
$messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get message counts
$counts = [];
$counts['inbox'] = query("SELECT COUNT(*) as count FROM messages WHERE receiver_id = $user_id AND deleted_by_receiver = 0")->fetch_assoc()['count'];
$counts['inbox_unread'] = query("SELECT COUNT(*) as count FROM messages WHERE receiver_id = $user_id AND deleted_by_receiver = 0 AND `read` = 0")->fetch_assoc()['count'];
$counts['sent'] = query("SELECT COUNT(*) as count FROM messages WHERE sender_id = $user_id AND deleted_by_sender = 0")->fetch_assoc()['count'];
$counts['trash'] = query("SELECT COUNT(*) as count FROM messages WHERE (sender_id = $user_id AND deleted_by_sender = 1) OR (receiver_id = $user_id AND deleted_by_receiver = 1)")->fetch_assoc()['count'];

// Get users for messaging
$users = [];
if (in_array($user_role, ['admin', 'doctor', 'nurse'])) {
    // Can message all staff and patients
    $result = query("SELECT id, name, role FROM users WHERE id != $user_id AND status = 'active' ORDER BY role, name");
} else {
    // Patients can only message doctors
    $result = query("SELECT u.id, u.name, u.role 
                     FROM users u 
                     JOIN doctors d ON u.id = d.user_id 
                     WHERE u.id != $user_id AND u.status = 'active' AND d.status = 'active' 
                     ORDER BY u.name");
}
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}

include '../includes/header.php';
?>

<!-- Message Folders -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <div class="btn-group" role="group">
                            <a href="?folder=inbox" class="btn <?php echo $folder === 'inbox' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                                <i class="fas fa-inbox me-2"></i>Inbox
                                <?php if ($counts['inbox_unread'] > 0): ?>
                                    <span class="badge bg-danger"><?php echo $counts['inbox_unread']; ?></span>
                                <?php endif; ?>
                            </a>
                            <a href="?folder=sent" class="btn <?php echo $folder === 'sent' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                                <i class="fas fa-paper-plane me-2"></i>Sent
                            </a>
                            <a href="?folder=trash" class="btn <?php echo $folder === 'trash' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                                <i class="fas fa-trash me-2"></i>Trash
                            </a>
                        </div>
                    </div>
                    <div class="col-md-4 text-end">
                        <a href="compose.php" class="btn btn-success">
                            <i class="fas fa-plus me-2"></i>Compose Message
                        </a>
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
                    <div class="col-md-4">
                        <label for="search" class="form-label">Search Messages</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" class="form-control" id="search" name="search" 
                                   value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Search by subject, content, sender...">
                        </div>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search me-2"></i>Search
                            </button>
                        </div>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-grid">
                            <a href="index.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-2"></i>Clear
                            </a>
                        </div>
                    </div>
                    
                    <div class="col-md-4 text-end">
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-outline-secondary" onclick="markAllRead()">
                                <i class="fas fa-envelope-open me-2"></i>Mark All Read
                            </button>
                            <button type="button" class="btn btn-outline-info" onclick="exportMessages()">
                                <i class="fas fa-download me-2"></i>Export
                            </button>
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
                <h5 class="mb-0">
                    <?php echo ucfirst($folder); ?> 
                    <small class="text-muted">(<?php echo $counts[$folder]; ?> messages)</small>
                </h5>
            </div>
            <div>
                <?php if ($folder === 'trash'): ?>
                    <button type="button" class="btn btn-danger" onclick="emptyTrash()">
                        <i class="fas fa-trash-alt me-2"></i>Empty Trash
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Messages List -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <?php if (empty($messages)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-envelope fa-3x text-muted mb-3"></i>
                        <h5>No Messages Found</h5>
                        <p class="text-muted">No messages in this folder.</p>
                        <a href="compose.php" class="btn btn-primary">Compose Message</a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th width="40">
                                        <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                                    </th>
                                    <th>From / To</th>
                                    <th>Subject</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($messages as $message): ?>
                                    <tr class="<?php echo ($message['receiver_id'] == $user_id && $message['read'] == 0) ? 'table-primary' : ''; ?>">
                                        <td>
                                            <input type="checkbox" class="message-checkbox" value="<?php echo $message['id']; ?>">
                                        </td>
                                        <td>
                                            <?php if ($folder === 'sent'): ?>
                                                <!-- Sent messages - show receiver -->
                                                <div class="fw-bold"><?php echo htmlspecialchars($message['receiver_name']); ?></div>
                                                <small class="text-muted"><?php echo ucfirst($message['receiver_role']); ?></small>
                                            <?php else: ?>
                                                <!-- Inbox/Trash - show sender -->
                                                <div class="fw-bold"><?php echo htmlspecialchars($message['sender_name']); ?></div>
                                                <small class="text-muted"><?php echo ucfirst($message['sender_role']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="<?php echo ($message['receiver_id'] == $user_id && $message['read'] == 0) ? 'fw-bold' : ''; ?>">
                                                <?php echo htmlspecialchars($message['subject'] ?: 'No Subject'); ?>
                                            </div>
                                            <small class="text-muted"><?php echo truncateText(strip_tags($message['message']), 50); ?></small>
                                        </td>
                                        <td>
                                            <div><?php echo formatDate($message['created_at']); ?></div>
                                            <small class="text-muted"><?php echo timeAgo($message['created_at']); ?></small>
                                        </td>
                                        <td>
                                            <?php if ($message['receiver_id'] == $user_id): ?>
                                                <?php echo $message['read'] ? 
                                                    '<span class="badge bg-success">Read</span>' : 
                                                    '<span class="badge bg-primary">Unread</span>'; ?>
                                            <?php else: ?>
                                                <span class="badge bg-info">Sent</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group" role="group">
                                                <a href="view.php?id=<?php echo $message['id']; ?>" class="btn btn-sm btn-outline-primary" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                
                                                <?php if ($folder === 'trash'): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-success" title="Restore" 
                                                            onclick="restoreMessage(<?php echo $message['id']; ?>)">
                                                        <i class="fas fa-undo"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-danger" title="Delete Permanently" 
                                                            onclick="deleteMessage(<?php echo $message['id']; ?>, true)">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary" title="Reply" 
                                                            onclick="replyMessage(<?php echo $message['id']; ?>)">
                                                        <i class="fas fa-reply"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-warning" title="Move to Trash" 
                                                            onclick="trashMessage(<?php echo $message['id']; ?>)">
                                                        <i class="fas fa-trash"></i>
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

<!-- Quick Compose Panel -->
<div class="row mt-4">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Quick Compose</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="send_quick.php">
                    <?php echo getCSRFInput(); ?>
                    <div class="mb-3">
                        <label for="quick_recipient" class="form-label">To *</label>
                        <select class="form-select" id="quick_recipient" name="recipient_id" required>
                            <option value="">Select Recipient</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>">
                                    <?php echo htmlspecialchars($user['name']); ?> (<?php echo ucfirst($user['role']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="quick_subject" class="form-label">Subject *</label>
                        <input type="text" class="form-control" id="quick_subject" name="subject" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="quick_message" class="form-label">Message *</label>
                        <textarea class="form-control" id="quick_message" name="message" rows="3" required></textarea>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane me-2"></i>Send Message
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Message Statistics</h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-3">
                        <div class="h4 text-primary"><?php echo $counts['inbox']; ?></div>
                        <small class="text-muted">Inbox Messages</small>
                    </div>
                    <div class="col-md-3">
                        <div class="h4 text-warning"><?php echo $counts['inbox_unread']; ?></div>
                        <small class="text-muted">Unread Messages</small>
                    </div>
                    <div class="col-md-3">
                        <div class="h4 text-success"><?php echo $counts['sent']; ?></div>
                        <small class="text-muted">Sent Messages</small>
                    </div>
                    <div class="col-md-3">
                        <div class="h4 text-danger"><?php echo $counts['trash']; ?></div>
                        <small class="text-muted">Trash Messages</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function toggleSelectAll() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.message-checkbox');
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAll.checked;
    });
}

function markAllRead() {
    const selectedIds = getSelectedMessageIds();
    
    if (selectedIds.length === 0) {
        alert('Please select messages to mark as read');
        return;
    }
    
    fetch('api/messages.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=mark_read&message_ids=${selectedIds.join(',')}&csrf_token=<?php echo generateCSRFToken(); ?>`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred');
    });
}

function getSelectedMessageIds() {
    const checkboxes = document.querySelectorAll('.message-checkbox:checked');
    return Array.from(checkboxes).map(cb => cb.value);
}

function replyMessage(messageId) {
    window.location.href = 'compose.php?reply_to=' + messageId;
}

function trashMessage(messageId) {
    if (confirm('Move this message to trash?')) {
        fetch('api/messages.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=trash&message_id=${messageId}&csrf_token=<?php echo generateCSRFToken(); ?>`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred');
        });
    }
}

function restoreMessage(messageId) {
    if (confirm('Restore this message from trash?')) {
        fetch('api/messages.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=restore&message_id=${messageId}&csrf_token=<?php echo generateCSRFToken(); ?>`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred');
        });
    }
}

function deleteMessage(messageId, permanent = false) {
    const message = permanent ? 'Delete this message permanently?' : 'Move this message to trash?';
    
    if (confirm(message)) {
        const action = permanent ? 'delete_permanent' : 'trash';
        
        fetch('api/messages.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=${action}&message_id=${messageId}&csrf_token=<?php echo generateCSRFToken(); ?>`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred');
        });
    }
}

function emptyTrash() {
    if (confirm('Empty trash permanently? This action cannot be undone.')) {
        fetch('api/messages.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=empty_trash&csrf_token=<?php echo generateCSRFToken(); ?>`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred');
        });
    }
}

function exportMessages() {
    const url = new URL(window.location.href);
    url.searchParams.set('export', '1');
    window.open(url.toString(), '_blank');
}

// Auto-refresh every 30 seconds for new messages
setInterval(() => {
    if (document.visibilityState === 'visible') {
        location.reload();
    }
}, 30000);

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    if (e.ctrlKey && e.key === 'n') {
        e.preventDefault();
        window.location.href = 'compose.php';
    }
});
</script>

<?php include '../includes/footer.php'; ?>
