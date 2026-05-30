<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Require login and appropriate role
requireLogin();
requireAnyRole(['admin', 'lab_technician', 'radiologist']);

$id = intval($_GET['id'] ?? 0);

if (!$id) {
    setNotification('Invalid scan ID.', 'danger');
    redirect('index.php');
}

// Verify request exists
$res = query("SELECT rr.*, rt.name as test_name, p.first_name, p.last_name, p.user_id as patient_user_id, d.user_id as doctor_user_id 
              FROM radiology_requests rr
              JOIN patients p ON rr.patient_id = p.id
              JOIN radiology_tests rt ON rr.test_id = rt.id
              LEFT JOIN doctors d ON rr.doctor_id = d.id
              WHERE rr.id = $id LIMIT 1");

if (!$res || numRows($res) === 0) {
    setNotification('Radiology request not found.', 'danger');
    redirect('index.php');
}

$request = $res->fetch_assoc();

if ($request['status'] !== 'scheduled' && $request['status'] !== 'pending') {
    setNotification('Scan cannot be started. Current status: ' . ucfirst($request['status']), 'warning');
    redirect('index.php');
}

// Update status to in_progress
$sql = "UPDATE radiology_requests SET status = 'in_progress' WHERE id = ?";
$stmt = prepare($sql);
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    logActivity('start_radiology_scan', "Started radiology scan ID $id");

    // Notify patient
    if ($request['patient_user_id']) {
        $p_uid = $request['patient_user_id'];
        $title = "Radiology Scan in Progress";
        $msg = "Your radiology scan (" . $request['test_name'] . ") is now in progress.";
        $notif_sql = "INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, 'system', ?)";
        $n_stmt = prepare($notif_sql);
        $link = "radiology/view.php?id=" . $id;
        $n_stmt->bind_param("isss", $p_uid, $title, $msg, $link);
        $n_stmt->execute();
    }

    setNotification('Scan started successfully.', 'success');
} else {
    setNotification('Failed to start scan: ' . $conn->error, 'danger');
}

redirect('index.php');
?>
