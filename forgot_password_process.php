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
            $reset_link = "http://" . $_SERVER['HTTP_HOST'] . "/reset_password.php?token=" . $reset_token;
            
            $to = $email;
            $subject = "Password Reset - Student Chatbot";
            $message = "Hello " . $user['name'] . ",\n\n";
            $message .= "You requested to reset your password. Please click the link below to reset your password:\n\n";
            $message .= $reset_link . "\n\n";
            $message .= "This link will expire in 1 hour.\n\n";
            $message .= "If you did not request a password reset, please ignore this email.\n\n";
            $message .= "Regards,\nStudent Chatbot Team";
            $headers = "From: noreply@studentchatbot.com";
            
            if (mail($to, $subject, $message, $headers)) {
                $_SESSION['success'] = "Password reset email sent! Please check your inbox.";
                header("Location: forgot_password.php");
                exit();
            } else {
                $_SESSION['error'] = "Failed to send password reset email. Please try again.";
                header("Location: forgot_password.php");
                exit();
            }
        } else {
            $_SESSION['error'] = "Failed to process password reset. Please try again.";
            header("Location: forgot_password.php");
            exit();
        }
        
        $update_stmt->close();
    } else {
        // We don't want to reveal if an email exists or not for security reasons
        // So we show a generic success message even if the email doesn't exist
        $_SESSION['success'] = "If your email exists in our system, you will receive a password reset link.";
        header("Location: forgot_password.php");
        exit();
    }
    
    $stmt->close();
} else {
    // Not a POST request
    header("Location: forgot_password.php");
    exit();
}

$conn->close();
?>