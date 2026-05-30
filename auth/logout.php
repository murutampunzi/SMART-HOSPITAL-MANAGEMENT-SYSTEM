<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (isLoggedIn()) {
    // Log activity
    logActivity('logout', 'User logged out: ' . $_SESSION['user_email']);
    
    // Clear session
    session_unset();
    session_destroy();
    
    // Clear remember me cookies
    if (isset($_COOKIE['remember_token'])) {
        setcookie('remember_token', '', time() - 3600, '/', '', SECURE, true);
    }
    if (isset($_COOKIE['email'])) {
        setcookie('email', '', time() - 3600, '/', '', SECURE, true);
    }
}

// Redirect to login page
redirect('index.php?success=You have been logged out successfully');
?>
