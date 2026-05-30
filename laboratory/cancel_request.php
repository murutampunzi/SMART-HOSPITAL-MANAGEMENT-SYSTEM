<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Require login and appropriate role
requireLogin();
requireAnyRole(['admin', 'doctor']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        redirect('index.php?error=Invalid security token. Please try again.');
    }
    
    $request_id = intval($_POST['request_id'] ?? 0);
    $cancel_reason = sanitizeInput($_POST['cancel_reason'] ?? '');
    
    if ($request_id <= 0 || empty($cancel_reason)) {
        redirect('index.php?error=Invalid request details provided');
    }
    
    // Fetch request details
    $stmt = prepare("SELECT * FROM lab_test_requests WHERE id = ?");
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    $request = $stmt->get_result()->fetch_assoc();
    
    if (!$request) {
        redirect('index.php?error=Lab request not found');
    }
    
    // Append cancellation notes to existing notes if any
    $notes = $request['notes'] ?? '';
    $notes .= "\n[Cancelled. Reason: " . $cancel_reason . "]";
    
    // Update status to cancelled
    $update_stmt = prepare("UPDATE lab_test_requests SET status = 'cancelled', notes = ?, updated_at = NOW() WHERE id = ?");
    $update_stmt->bind_param("si", $notes, $request_id);
    
    if ($update_stmt->execute()) {
        logActivity('lab_request_cancelled', 'Cancelled lab request ' . $request['request_id'] . '. Reason: ' . $cancel_reason);
        redirect('index.php?success=Lab request cancelled successfully');
    } else {
        redirect('index.php?error=Failed to cancel lab request. Please try again.');
    }
} else {
    redirect('index.php');
}
?>
