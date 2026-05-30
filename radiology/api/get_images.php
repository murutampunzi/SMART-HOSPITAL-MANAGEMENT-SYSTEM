<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Require login and appropriate role
requireLogin();
if (!in_array($_SESSION['user_role'], ['admin', 'doctor', 'lab_technician', 'radiologist'])) {
    errorResponse('Access denied');
}

$request_id = intval($_GET['request_id'] ?? 0);

if (!$request_id) {
    errorResponse('Invalid request ID');
}

// Fetch images from database
$sql = "SELECT id, image_path, image_type, image_description, upload_date 
        FROM radiology_images 
        WHERE request_id = ? 
        ORDER BY upload_date ASC";

$stmt = prepare($sql);
$stmt->bind_param("i", $request_id);
$stmt->execute();
$result = $stmt->get_result();

$images = [];
while ($row = $result->fetch_assoc()) {
    $images[] = [
        'id' => $row['id'],
        'path' => BASE_PATH . $row['image_path'],
        'type' => $row['image_type'],
        'description' => $row['image_description'] ?: 'No description provided',
        'upload_date' => $row['upload_date']
    ];
}

successResponse('Images loaded successfully', ['images' => $images]);
?>
