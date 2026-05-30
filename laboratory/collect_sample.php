<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Require login and appropriate role
requireLogin();
requireAnyRole(['admin', 'lab_technician']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        redirect('index.php?error=Invalid security token. Please try again.');
    }
    
    $request_id = intval($_POST['request_id'] ?? 0);
    $sample_notes = sanitizeInput($_POST['sample_notes'] ?? '');
    
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
    
    // Append sample collection notes to existing notes if any
    $notes = $request['notes'] ?? '';
    if (!empty($sample_notes)) {
        $notes .= "\n[Sample Collection Notes: " . $sample_notes . "]";
    }
    
    // Update status to sample_collected
    $user_id = intval($_SESSION['user_id']);
    $update_stmt = prepare("UPDATE lab_test_requests SET status = 'sample_collected', sample_collected = 1, sample_collected_date = NOW(), sample_collected_by = ?, notes = ?, updated_at = NOW() WHERE id = ?");
    $update_stmt->bind_param("isi", $user_id, $notes, $request_id);
    
    if ($update_stmt->execute()) {
        logActivity('lab_sample_collected', 'Collected Specimen sample for request: ' . $request['request_id']);
        redirect('index.php?success=Specimen sample collected and logged successfully');
    } else {
        redirect('index.php?error=Failed to update sample collection details. Please try again.');
    }
} else {
    redirect('index.php');
}
?>
