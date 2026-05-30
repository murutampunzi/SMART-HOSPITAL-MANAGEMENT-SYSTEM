<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Set JSON header
header('Content-Type: application/json');

// Require login for API access
requireLogin();

// Get search parameters
$query = sanitizeInput($_GET['q'] ?? '');
$type = sanitizeInput($_GET['type'] ?? '');
$limit = min(20, intval($_GET['limit'] ?? 10));

if (empty($query)) {
    echo json_encode(['error' => 'Search query is required']);
    exit;
}

$results = [];

// Search based on type
switch ($type) {
    case 'patients':
        $sql = "SELECT id, patient_id, first_name, last_name, phone, email 
                FROM patients 
                WHERE (first_name LIKE ? OR last_name LIKE ? OR patient_id LIKE ? OR phone LIKE ?) 
                AND status = 'active' 
                LIMIT ?";
        $search_param = "%$query%";
        $stmt = prepare($sql);
        $stmt->bind_param("ssssi", $search_param, $search_param, $search_param, $search_param, $limit);
        $stmt->execute();
        $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        break;
        
    case 'doctors':
        $sql = "SELECT id, doctor_id, first_name, last_name, specialization, phone 
                FROM doctors 
                WHERE (first_name LIKE ? OR last_name LIKE ? OR doctor_id LIKE ? OR specialization LIKE ?) 
                AND status = 'active' 
                LIMIT ?";
        $search_param = "%$query%";
        $stmt = prepare($sql);
        $stmt->bind_param("ssssi", $search_param, $search_param, $search_param, $search_param, $limit);
        $stmt->execute();
        $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        break;
        
    case 'medicines':
        $sql = "SELECT id, medicine_id, name, generic_name, category, selling_price 
                FROM medicines 
                WHERE (name LIKE ? OR generic_name LIKE ? OR medicine_id LIKE ? OR category LIKE ?) 
                AND status = 'active' 
                LIMIT ?";
        $search_param = "%$query%";
        $stmt = prepare($sql);
        $stmt->bind_param("ssssi", $search_param, $search_param, $search_param, $search_param, $limit);
        $stmt->execute();
        $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        break;
        
    case 'lab_tests':
        $sql = "SELECT id, code, name, category, price 
                FROM lab_tests 
                WHERE (name LIKE ? OR code LIKE ? OR category LIKE ?) 
                AND status = 'active' 
                LIMIT ?";
        $search_param = "%$query%";
        $stmt = prepare($sql);
        $stmt->bind_param("sssi", $search_param, $search_param, $search_param, $limit);
        $stmt->execute();
        $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        break;
        
    default:
        // Global search across all types
        $patients = query("SELECT id, patient_id, first_name, last_name, 'patient' as type 
                          FROM patients 
                          WHERE (first_name LIKE '%$query%' OR last_name LIKE '%$query%' OR patient_id LIKE '%$query%') 
                          AND status = 'active' LIMIT 5")->fetch_all(MYSQLI_ASSOC);
        
        $doctors = query("SELECT id, doctor_id, first_name, last_name, 'doctor' as type 
                          FROM doctors 
                          WHERE (first_name LIKE '%$query%' OR last_name LIKE '%$query%' OR doctor_id LIKE '%$query%') 
                          AND status = 'active' LIMIT 5")->fetch_all(MYSQLI_ASSOC);
        
        $results = array_merge($patients, $doctors);
        break;
}

echo json_encode([
    'success' => true,
    'query' => $query,
    'type' => $type,
    'count' => count($results),
    'results' => $results
]);
