<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Set JSON header
header('Content-Type: application/json');

// Require login for API access
requireLogin();

$user_id = $_SESSION['user_id'];
$action = sanitizeInput($_GET['action'] ?? '');

switch ($action) {
    case 'profile':
        // Get user profile
        $sql = "SELECT id, name, email, role, phone, address, status, created_at 
                FROM users WHERE id = ?";
        $stmt = prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        
        if ($user) {
            // Remove sensitive data
            unset($user['password']);
            
            // Get role-specific data
            if ($user['role'] === 'patient') {
                $patient_sql = "SELECT * FROM patients WHERE user_id = ?";
                $patient_stmt = prepare($patient_sql);
                $patient_stmt->bind_param("i", $user_id);
                $patient_stmt->execute();
                $patient = $patient_stmt->get_result()->fetch_assoc();
                $user['patient_data'] = $patient;
            } elseif ($user['role'] === 'doctor') {
                $doctor_sql = "SELECT * FROM doctors WHERE user_id = ?";
                $doctor_stmt = prepare($doctor_sql);
                $doctor_stmt->bind_param("i", $user_id);
                $doctor_stmt->execute();
                $doctor = $doctor_stmt->get_result()->fetch_assoc();
                $user['doctor_data'] = $doctor;
            }
            
            echo json_encode(['success' => true, 'user' => $user]);
        } else {
            echo json_encode(['success' => false, 'error' => 'User not found']);
        }
        break;
        
    case 'update_profile':
        // Update user profile
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            exit;
        }
        
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
            exit;
        }
        
        $name = sanitizeInput($_POST['name'] ?? '');
        $phone = sanitizeInput($_POST['phone'] ?? '');
        $address = sanitizeInput($_POST['address'] ?? '');
        
        if (empty($name)) {
            echo json_encode(['success' => false, 'error' => 'Name is required']);
            exit;
        }
        
        try {
            $sql = "UPDATE users SET name = ?, phone = ?, address = ? WHERE id = ?";
            $stmt = prepare($sql);
            $stmt->bind_param("sssi", $name, $phone, $address, $user_id);
            
            if (!$stmt->execute()) {
                throw new Exception('Failed to update profile');
            }
            
            logActivity('profile_updated', "User updated their profile");
            
            echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;
        
    case 'change_password':
        // Change password
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            exit;
        }
        
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
            exit;
        }
        
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            echo json_encode(['success' => false, 'error' => 'All password fields are required']);
            exit;
        }
        
        if ($new_password !== $confirm_password) {
            echo json_encode(['success' => false, 'error' => 'New passwords do not match']);
            exit;
        }
        
        if (strlen($new_password) < 8) {
            echo json_encode(['success' => false, 'error' => 'Password must be at least 8 characters']);
            exit;
        }
        
        try {
            // Verify current password
            $sql = "SELECT password FROM users WHERE id = ?";
            $stmt = prepare($sql);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            
            if (!password_verify($current_password, $user['password'])) {
                throw new Exception('Current password is incorrect');
            }
            
            // Update password
            $hashed_password = hashPassword($new_password);
            $update_sql = "UPDATE users SET password = ? WHERE id = ?";
            $update_stmt = prepare($update_sql);
            $update_stmt->bind_param("si", $hashed_password, $user_id);
            
            if (!$update_stmt->execute()) {
                throw new Exception('Failed to update password');
            }
            
            logActivity('password_changed', "User changed their password");
            
            echo json_encode(['success' => true, 'message' => 'Password changed successfully']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;
        
    case 'notifications':
        // Get user notifications
        $sql = "SELECT * FROM notifications 
                WHERE user_id = ? AND status = 'unread' 
                ORDER BY created_at DESC LIMIT 10";
        $stmt = prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        echo json_encode(['success' => true, 'notifications' => $notifications]);
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}
