<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Require login
requireLogin();

$result_id = intval($_GET['id'] ?? 0);
if ($result_id <= 0) {
    redirect('index.php?error=Invalid diagnostic result ID');
}

// Fetch the request_id from lab_results table
$stmt = prepare("SELECT request_id FROM lab_results WHERE id = ?");
$stmt->bind_param("i", $result_id);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();

if ($res) {
    redirect('view.php?id=' . $res['request_id']);
} else {
    redirect('index.php?error=Diagnostic result not found');
}
