<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Require login and appropriate role
requireLogin();
requireAnyRole(['admin', 'doctor']);

$id = intval($_GET['id'] ?? 0);

if (!$id) {
    setNotification('Invalid request ID.', 'danger');
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

if ($request['status'] === 'completed' || $request['status'] === 'cancelled') {
    setNotification('Scan cannot be cancelled. Current status: ' . ucfirst($request['status']), 'warning');
    redirect('index.php');
}

// Determine cancelled_by role
$cancelled_by = 'admin';
if (hasRole('doctor')) {
    $cancelled_by = 'doctor';
}

$reason = "Cancelled by " . ($_SESSION['user_name'] ?? 'Administrator');

// Update request status to cancelled
$sql = "UPDATE radiology_requests 
        SET status = 'cancelled', 
            cancelled_by = ?, 
            cancellation_reason = ? 
        WHERE id = ?";
$stmt = prepare($sql);
$stmt->bind_param("ssi", $cancelled_by, $reason, $id);

if ($stmt->execute()) {
    logActivity('cancel_radiology_request', "Cancelled radiology request ID $id");

    // Notify patient
    if ($request['patient_user_id']) {
        $p_uid = $request['patient_user_id'];
        $title = "Radiology Scan Cancelled";
        $msg = "Your radiology scan request (" . $request['test_name'] . ") has been cancelled.";
        $notif_sql = "INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, 'system', ?)";
        $n_stmt = prepare($notif_sql);
        $link = "radiology/view.php?id=" . $id;
        $n_stmt->bind_param("isss", $p_uid, $title, $msg, $link);
        $n_stmt->execute();
    }

    // Notify Referring Doctor if cancelled by Admin
    if ($cancelled_by === 'admin' && $request['doctor_user_id']) {
        $d_uid = $request['doctor_user_id'];
        $title = "Radiology Scan Cancelled by Admin";
        $msg = "Scan request (" . $request['test_name'] . ") for " . $request['first_name'] . " " . $request['last_name'] . " was cancelled by the administrator.";
        $notif_sql = "INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, 'system', ?)";
        $n_stmt = prepare($notif_sql);
        $link = "radiology/view.php?id=" . $id;
        $n_stmt->bind_param("isss", $d_uid, $title, $msg, $link);
        $n_stmt->execute();
    }

    setNotification('Scan request cancelled successfully.', 'success');
} else {
    setNotification('Failed to cancel scan request: ' . $conn->error, 'danger');
}

redirect('index.php');
?>
