<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Require login and appropriate role
requireLogin();
requireAnyRole(['admin', 'lab_technician']);

$request_id = intval($_GET['id'] ?? 0);
if ($request_id <= 0) {
    redirect('index.php?error=Invalid request ID');
}

// Fetch request details
$stmt = prepare("SELECT * FROM lab_test_requests WHERE id = ?");
$stmt->bind_param("i", $request_id);
$stmt->execute();
$request = $stmt->get_result()->fetch_assoc();

if (!$request) {
    redirect('index.php?error=Lab request not found');
}

// Update status to in_progress
$update = query("UPDATE lab_test_requests SET status = 'in_progress', updated_at = NOW() WHERE id = $request_id");

if ($update) {
    logActivity('lab_test_started', 'Started diagnostic testing for request: ' . $request['request_id']);
    redirect('index.php?success=Lab test request is now in progress');
} else {
    redirect('index.php?error=Failed to start diagnostic test. Please try again.');
}
?>
