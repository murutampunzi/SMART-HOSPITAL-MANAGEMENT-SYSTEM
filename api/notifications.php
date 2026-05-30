<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Set content type to JSON
header('Content-Type: application/json');

// Require login
requireLogin();

// Get request method
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            // Get notifications for current user
            $user_id = $_SESSION['user_id'];
            $limit = intval($_GET['limit'] ?? 20);
            $unread_only = isset($_GET['unread']) && $_GET['unread'] === 'true';
            
            $sql = "SELECT n.*, 
                           CASE 
                               WHEN n.type = 'appointment' THEN CONCAT('appointments/view.php?id=', SUBSTRING_INDEX(SUBSTRING_INDEX(n.message, 'ID:', -1), ')', '')
                               WHEN n.type = 'message' THEN 'messages/index.php'
                               WHEN n.type = 'lab_result' THEN CONCAT('laboratory/results.php?id=', SUBSTRING_INDEX(SUBSTRING_INDEX(n.message, 'ID:', -1), ')', '')
                               WHEN n.type = 'prescription' CONCAT('prescriptions/index.php')
                               WHEN n.type = 'payment' CONCAT('billing/index.php')
                               ELSE '#'
                           END as link
                    FROM notifications n 
                    WHERE n.user_id = ?" . ($unread_only ? " AND n.read = 0" : "") . "
                    ORDER BY n.created_at DESC 
                    LIMIT ?";
            
            $stmt = prepare($sql);
            $stmt->bind_param("ii", $user_id, $limit);
            $stmt->execute();
            $notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            
            // Format notifications
            foreach ($notifications as &$notification) {
                $notification['created_at_formatted'] = timeAgo($notification['created_at']);
                $notification['message'] = htmlspecialchars($notification['message']);
                $notification['title'] = htmlspecialchars($notification['title']);
            }
            
            successResponse('Notifications retrieved successfully', $notifications);
            break;
            
        case 'POST':
            // Create notification or mark as read
            $action = $_POST['action'] ?? '';
            
            if ($action === 'mark_read') {
                // Mark notification as read
                $notification_id = intval($_POST['notification_id'] ?? 0);
                $user_id = $_SESSION['user_id'];
                
                if ($notification_id <= 0) {
                    errorResponse('Invalid notification ID');
                }
                
                $sql = "UPDATE notifications SET read = 1, read_at = NOW() WHERE id = ? AND user_id = ?";
                $stmt = prepare($sql);
                $stmt->bind_param("ii", $notification_id, $user_id);
                
                if ($stmt->execute()) {
                    successResponse('Notification marked as read');
                } else {
                    errorResponse('Failed to mark notification as read');
                }
                
            } elseif ($action === 'mark_all_read') {
                // Mark all notifications as read for user
                $user_id = $_SESSION['user_id'];
                
                $sql = "UPDATE notifications SET read = 1, read_at = NOW() WHERE user_id = ? AND read = 0";
                $stmt = prepare($sql);
                $stmt->bind_param("i", $user_id);
                
                if ($stmt->execute()) {
                    $marked_count = $stmt->affected_rows;
                    successResponse("Marked $marked_count notifications as read");
                } else {
                    errorResponse('Failed to mark notifications as read');
                }
                
            } elseif ($action === 'create') {
                // Create new notification (admin only)
                if (!hasRole('admin')) {
                    errorResponse('Access denied', 403);
                }
                
                $target_user_id = intval($_POST['user_id'] ?? 0);
                $title = sanitizeInput($_POST['title'] ?? '');
                $message = sanitizeInput($_POST['message'] ?? '');
                $type = sanitizeInput($_POST['type'] ?? 'system');
                $link = sanitizeInput($_POST['link'] ?? '');
                
                if ($target_user_id <= 0 || empty($title) || empty($message)) {
                    errorResponse('Missing required fields');
                }
                
                $sql = "INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, ?, ?)";
                $stmt = prepare($sql);
                $stmt->bind_param("issss", $target_user_id, $title, $message, $type, $link);
                
                if ($stmt->execute()) {
                    $notification_id = insertId();
                    successResponse('Notification created successfully', ['notification_id' => $notification_id]);
                } else {
                    errorResponse('Failed to create notification');
                }
                
            } else {
                errorResponse('Invalid action');
            }
            break;
            
        case 'DELETE':
            // Delete notification
            if (!hasRole('admin')) {
                errorResponse('Access denied', 403);
            }
            
            $notification_id = intval($_GET['id'] ?? 0);
            
            if ($notification_id <= 0) {
                errorResponse('Invalid notification ID');
            }
            
            $sql = "DELETE FROM notifications WHERE id = ?";
            $stmt = prepare($sql);
            $stmt->bind_param("i", $notification_id);
            
            if ($stmt->execute()) {
                successResponse('Notification deleted successfully');
            } else {
                errorResponse('Failed to delete notification');
            }
            break;
            
        default:
            errorResponse('Method not allowed', 405);
    }
    
} catch (Exception $e) {
    errorResponse('Server error: ' . $e->getMessage(), 500);
}
?>
