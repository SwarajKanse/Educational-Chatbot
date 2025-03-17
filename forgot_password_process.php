<?php
session_start();
require_once 'db_connect.php';
require_once 'functions.php';

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    
    // Basic validation
    if (empty($email)) {
        $_SESSION['error'] = "Please enter your email address";
        header("Location: forgot_password.php");
        exit();
    }
    
    // Check if email exists
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? AND auth_method = 'email'");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        
        // Generate reset token
        $reset_token = bin2hex(random_bytes(32));
        $token_expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        // Update user with reset token
        $update_stmt = $conn->prepare("UPDATE users SET reset_token = ?, token_expiry = ? WHERE id = ?");
        $update_stmt->bind_param("ssi", $reset_token, $token_expiry, $user['id']);
        
        if ($update_stmt->execute()) {
            // Send reset email
            $reset_link = "http://" . $_SERVER['HTTP_HOST'] . "/reset_