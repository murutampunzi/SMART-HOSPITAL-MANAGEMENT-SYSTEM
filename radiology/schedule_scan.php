<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Require login and appropriate role
requireLogin();
requireAnyRole(['admin', 'lab_technician', 'radiologist']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        setNotification('Invalid request token.', 'danger');
        redirect('index.php');
    }

    $request_id = intval($_POST['request_id'] ?? 0);
    $scheduled_date = sanitizeInput($_POST['scheduled_date'] ?? '');
    $scheduled_time = sanitizeInput($_POST['scheduled_time'] ?? '');
    $technician_notes = sanitizeInput($_POST['technician_notes'] ?? '');

    if (empty($request_id) || empty($scheduled_date) || empty($scheduled_time)) {
        setNotification('Please fill in all required fields.', 'danger');
        redirect('index.php');
    }

    // Verify request exists
    $res = query("SELECT rr.*, rt.name as test_name, p.first_name, p.last_name, p.user_id as patient_user_id, d.user_id as doctor_user_id 
                  FROM radiology_requests rr
                  JOIN patients p ON rr.patient_id = p.id
                  JOIN radiology_tests rt ON rr.test_id = rt.id
                  LEFT JOIN doctors d ON rr.doctor_id = d.id
                  WHERE rr.id = $request_id LIMIT 1");
    
    if (!$res || numRows($res) === 0) {
        setNotification('Radiology request not found.', 'danger');
        redirect('index.php');
    }

    $request = $res->fetch_assoc();

    // Prepare note append
    $note_append = "";
    if (!empty($technician_notes)) {
        $note_append = "\n[Schedule Notes]: " . $technician_notes;
    }

    $sql = "UPDATE radiology_requests 
            SET status = 'scheduled', 
                scheduled_date = ?, 
                scheduled_time = ?, 
                notes = CONCAT(COALESCE(notes, ''), ?) 
            WHERE id = ?";
    
    $stmt = prepare($sql);
    $stmt->bind_param("sssi", $scheduled_date, $scheduled_time, $note_append, $request_id);

    if ($stmt->execute()) {
        logActivity('schedule_radiology_scan', "Scheduled radiology scan ID $request_id for $scheduled_date at $scheduled_time");

        // Notify patient
        if ($request['patient_user_id']) {
            $p_uid = $request['patient_user_id'];
            $title = "Radiology Scan Scheduled";
            $msg = "Your radiology scan (" . $request['test_name'] . ") has been scheduled for " . formatDate($scheduled_date) . " at " . date('h:i A', strtotime($scheduled_time)) . ".";
            $notif_sql = "INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, 'system', ?)";
            $n_stmt = prepare($notif_sql);
            $link = "radiology/view.php?id=" . $request_id;
            $n_stmt->bind_param("isss", $p_uid, $title, $msg, $link);
            $n_stmt->execute();
        }

        // Notify referring doctor
        if ($request['doctor_user_id']) {
            $d_uid = $request['doctor_user_id'];
            $title = "Patient Radiology Scan Scheduled";
            $msg = "Radiology scan (" . $request['test_name'] . ") for " . $request['first_name'] . " " . $request['last_name'] . " has been scheduled for " . formatDate($scheduled_date) . " at " . date('h:i A', strtotime($scheduled_time)) . ".";
            $notif_sql = "INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, 'appointment', ?)";
            $n_stmt = prepare($notif_sql);
            $link = "radiology/view.php?id=" . $request_id;
            $n_stmt->bind_param("isss", $d_uid, $title, $msg, $link);
            $n_stmt->execute();
        }

        setNotification('Scan scheduled successfully.', 'success');
    } else {
        setNotification('Failed to schedule scan: ' . $conn->error, 'danger');
    }
}

redirect('index.php');
?>
