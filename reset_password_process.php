<?php
session_start();
require_once 'db_connect.php';
require_once 'functions.php';

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $token = $_POST['token'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm-password'];
    
    // Basic validation
    if (empty($token) || empty($password) || empty($confirm_password)) {
        $_SESSION['error'] = "Please fill in all fields";
        header("Location: reset_password.php?token=" . urlencode($token));
        exit();
    }
    
    // Check if passwords match
    if ($password !== $confirm_password) {
        $_SESSION['error'] = "Passwords do not match";
        header("Location: reset_password.php?token=" . urlencode($token));
        exit();
    }
    
    // Password complexity validation
    if (strlen($password) < 8 || !preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/', $password)) {
        $_SESSION['error'] = "Password must be at least 8 characters with uppercase, lowercase, number, and special character";
        header("Location: reset_password.php?token=" . urlencode($token));
        exit();
    }
    
    // Check if token exists and is valid
    $stmt = $conn->prepare("SELECT * FROM users WHERE reset_token = ? AND token_expiry > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        
        // Hash new password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Update user with new password and clear token
        $update_stmt = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, token_expiry = NULL WHERE id = ?");
        $update_stmt->bind_param("si", $hashed_password, $user['id']);
        
        if ($update_stmt->execute()) {
            $_SESSION['success'] = "Password reset successfully! You can now log in with your new password.";
            header("Location: login.html");
            exit();
        } else {
            $_SESSION['error'] = "Failed to reset password. Please try again.";
            header("Location: reset_password.php?token=" . urlencode($token));
            exit();
        }
        
        $update_stmt->close();
    } else {
        // Invalid or expired token
        $_SESSION['error'] = "Invalid or expired password reset link. Please request a new one.";
        header("Location: forgot_password.php");
        exit();
    }
    
    $stmt->close();
} else {
    // Not a POST request
    header("Location: login.html");
    exit();
}

$conn->close();
?>