<?php
// Security functions
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validatePassword($password) {
    return strlen($password) >= 8;
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT, ['cost' => HASH_COST]);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// Authentication functions
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        $base = defined('BASE_PATH') ? BASE_PATH : '/SMART/';
        redirect($base . 'index.php?error=Please login to access this page');
    }
}

function hasRole($role) {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === $role;
}

function requireRole($role) {
    requireLogin();
    if (!hasRole($role)) {
        $base = defined('BASE_PATH') ? BASE_PATH : '/SMART/';
        redirect($base . 'dashboard.php?error=Access denied');
    }
}

function requireAnyRole($roles) {
    requireLogin();
    if (!in_array($_SESSION['user_role'], $roles)) {
        $base = defined('BASE_PATH') ? BASE_PATH : '/SMART/';
        redirect($base . 'dashboard.php?error=Access denied');
    }
}

function getCurrentUser() {
    if (isLoggedIn()) {
        global $conn;
        $user_id = $_SESSION['user_id'];
        $sql = "SELECT * FROM users WHERE id = ?";
        $stmt = prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }
    return null;
}

// URL and redirect functions
function redirect($url) {
    if (!preg_match('/^(https?:\/\/|\/)/i', $url)) {
        $parts = parse_url($url);
        $path = $parts['path'] ?? '';
        
        $current_dir = dirname($_SERVER['SCRIPT_FILENAME']);
        $base_path = defined('BASE_PATH') ? BASE_PATH : '/SMART/';
        
        // If the file exists in the current script's directory, redirect relative to it
        if ($path !== '' && file_exists($current_dir . '/' . $path)) {
            $current_url_dir = dirname($_SERVER['SCRIPT_NAME']);
            $current_url_dir = rtrim(str_replace('\\', '/', $current_url_dir), '/');
            $url = $current_url_dir . '/' . $url;
        } else {
            // Otherwise, resolve relative to the project root directory using BASE_PATH
            $url = $base_path . $url;
        }
    }
    
    // Clean up duplicate slashes except in protocol prefixes
    $url = preg_replace('/([^:])(\/{2,})/', '$1/', $url);
    
    header("Location: $url");
    exit();
}

function getCurrentURL() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
    return "$protocol://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
}

// Date and time functions
function formatDateTime($datetime, $format = 'Y-m-d H:i:s') {
    $date = new DateTime($datetime);
    return $date->format($format);
}

function formatDate($date, $format = 'Y-m-d') {
    return formatDateTime($date, $format);
}

function timeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return "Just now";
    } elseif ($diff < 3600) {
        return floor($diff / 60) . " minutes ago";
    } elseif ($diff < 86400) {
        return floor($diff / 3600) . " hours ago";
    } elseif ($diff < 604800) {
        return floor($diff / 86400) . " days ago";
    } else {
        return formatDate($datetime);
    }
}

// File upload functions
function uploadFile($file, $destination, $allowedTypes = []) {
    if (empty($allowedTypes)) {
        $allowedTypes = ALLOWED_FILE_TYPES;
    }
    
    $fileName = $file['name'];
    $fileSize = $file['size'];
    $fileTmpName = $file['tmp_name'];
    $fileError = $file['error'];
    
    // Check if file was uploaded
    if ($fileError !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'File upload error'];
    }
    
    // Check file size
    if ($fileSize > MAX_FILE_SIZE) {
        return ['success' => false, 'message' => 'File size exceeds maximum limit'];
    }
    
    // Check file type
    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    if (!in_array($fileExt, $allowedTypes)) {
        return ['success' => false, 'message' => 'File type not allowed'];
    }
    
    // Generate unique filename
    $newFileName = uniqid() . '.' . $fileExt;
    $uploadPath = $destination . '/' . $newFileName;
    
    // Create directory if not exists
    if (!is_dir($destination)) {
        mkdir($destination, 0755, true);
    }
    
    // Move file
    if (move_uploaded_file($fileTmpName, $uploadPath)) {
        return ['success' => true, 'filename' => $newFileName, 'path' => $uploadPath];
    } else {
        return ['success' => false, 'message' => 'Failed to move uploaded file'];
    }
}

// Pagination functions
function getPagination($totalItems, $itemsPerPage = ITEMS_PER_PAGE, $currentPage = 1) {
    $totalPages = ceil($totalItems / $itemsPerPage);
    $currentPage = max(1, min($currentPage, $totalPages));
    $offset = ($currentPage - 1) * $itemsPerPage;
    
    return [
        'total_items' => $totalItems,
        'items_per_page' => $itemsPerPage,
        'total_pages' => $totalPages,
        'current_page' => $currentPage,
        'offset' => $offset,
        'has_next' => $currentPage < $totalPages,
    ];
}

/**
 * Retrieve radiology reports data.
 *
 * @param string $status Filter reports by status (default 'completed').
 * @return array List of reports with patient, test, and radiologist info.
 */
function getRadiologyReports($status = 'completed') {
    global $conn;
    $sql = "SELECT rr.request_id, p.first_name, p.last_name, rt.name AS test_name, rt.modality, rr.requested_date, rri.report_date, u_rad.name AS radiologist_name
            FROM radiology_requests rr
            JOIN patients p ON rr.patient_id = p.id
            JOIN radiology_tests rt ON rr.test_id = rt.id
            LEFT JOIN radiology_images rri ON rr.id = rri.request_id
            LEFT JOIN users u_rad ON rri.radiologist_id = u_rad.id
            WHERE rr.status = ?
            ORDER BY rri.report_date DESC";
    $stmt = prepare($sql);
    $stmt->bind_param('s', $status);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}
        'has_prev' => $currentPage > 1
    ];
}

function renderPagination($pagination, $urlPattern) {
    $currentPage = $pagination['current_page'];
    $totalPages = $pagination['total_pages'];
    
    if ($totalPages <= 1) return '';
    
    $html = '<nav aria-label="Page navigation"><ul class="pagination justify-content-center">';
    
    // Previous button
    if ($pagination['has_prev']) {
        $prevUrl = str_replace('{page}', $currentPage - 1, $urlPattern);
        $html .= '<li class="page-item"><a class="page-link" href="' . $prevUrl . '">Previous</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><span class="page-link">Previous</span></li>';
    }
    
    // Page numbers
    $startPage = max(1, $currentPage - 2);
    $endPage = min($totalPages, $currentPage + 2);
    
    if ($startPage > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . str_replace('{page}', 1, $urlPattern) . '">1</a></li>';
        if ($startPage > 2) {
            $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
    }
    
    for ($i = $startPage; $i <= $endPage; $i++) {
        $pageUrl = str_replace('{page}', $i, $urlPattern);
        if ($i == $currentPage) {
            $html .= '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
        } else {
            $html .= '<li class="page-item"><a class="page-link" href="' . $pageUrl . '">' . $i . '</a></li>';
        }
    }
    
    if ($endPage < $totalPages) {
        if ($endPage < $totalPages - 1) {
            $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
        $html .= '<li class="page-item"><a class="page-link" href="' . str_replace('{page}', $totalPages, $urlPattern) . '">' . $totalPages . '</a></li>';
    }
    
    // Next button
    if ($pagination['has_next']) {
        $nextUrl = str_replace('{page}', $currentPage + 1, $urlPattern);
        $html .= '<li class="page-item"><a class="page-link" href="' . $nextUrl . '">Next</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><span class="page-link">Next</span></li>';
    }
    
    $html .= '</ul></nav>';
    
    return $html;
}

// Notification functions
function setNotification($message, $type = 'info') {
    $_SESSION['notification'] = ['message' => $message, 'type' => $type];
}

function getNotification() {
    if (isset($_SESSION['notification'])) {
        $notification = $_SESSION['notification'];
        unset($_SESSION['notification']);
        return $notification;
    }
    return null;
}

function displayNotification() {
    $notification = getNotification();
    if ($notification) {
        $alertClass = 'alert-' . $notification['type'];
        echo '<div class="alert ' . $alertClass . ' alert-dismissible fade show" role="alert">';
        echo htmlspecialchars($notification['message']);
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        echo '</div>';
    }
}

// Validation functions
function validateRequired($fields) {
    $errors = [];
    foreach ($fields as $field => $value) {
        if (empty(trim($value))) {
            $errors[$field] = ucfirst($field) . ' is required';
        }
    }
    return $errors;
}

function validateLength($field, $value, $min, $max) {
    $length = strlen(trim($value));
    if ($length < $min || $length > $max) {
        return ucfirst($field) . ' must be between ' . $min . ' and ' . $max . ' characters';
    }
    return null;
}

function validateDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

function validateNumeric($field, $value) {
    if (!is_numeric($value)) {
        return ucfirst($field) . ' must be a number';
    }
    return null;
}

// Utility functions
function generateUniqueId($prefix = '') {
    return $prefix . uniqid() . '-' . rand(1000, 9999);
}

function formatCurrency($amount, $currency = '$') {
    return $currency . number_format($amount, 2);
}

function getStatusBadge($status, $type = 'primary') {
    $statusColors = [
        'pending' => 'warning',
        'approved' => 'success',
        'rejected' => 'danger',
        'completed' => 'success',
        'cancelled' => 'danger',
        'active' => 'success',
        'inactive' => 'secondary',
        'normal' => 'success',
        'abnormal' => 'warning',
        'critical' => 'danger'
    ];
    
    $color = isset($statusColors[$status]) ? $statusColors[$status] : $type;
    return '<span class="badge bg-' . $color . '">' . ucfirst($status) . '</span>';
}

function truncateText($text, $length = 50) {
    if (strlen($text) <= $length) {
        return $text;
    }
    return substr($text, 0, $length) . '...';
}

// Logging functions
function logActivity($action, $details = '') {
    global $conn;
    
    if (!isLoggedIn()) return;
    
    $user_id = $_SESSION['user_id'];
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    
    $sql = "INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())";
    
    $stmt = prepare($sql);
    $stmt->bind_param("issss", $user_id, $action, $details, $ip_address, $user_agent);
    $stmt->execute();
}

// Email functions (basic implementation)
function sendEmail($to, $subject, $message, $from = SUPPORT_EMAIL) {
    $headers = "From: $from\r\n";
    $headers .= "Reply-To: $from\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    return mail($to, $subject, $message, $headers);
}

// API response functions
function jsonResponse($data, $status = 200) {
    header('Content-Type: application/json');
    http_response_code($status);
    echo json_encode($data);
    exit();
}

function successResponse($message, $data = null) {
    jsonResponse([
        'success' => true,
        'message' => $message,
        'data' => $data
    ]);
}

function errorResponse($message, $status = 400) {
    jsonResponse([
        'success' => false,
        'message' => $message
    ], $status);
}
?>
