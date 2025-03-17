<?php
session_start();
require_once 'db_connect.php';
require_once 'functions.php';

// Check if email is provided
if (!isset($_GET['email']) || empty($_GET['email'])) {
    $_SESSION['error'] = "Email is required";
    header("Location: login.html");
    exit();
}

$email = $_GET['email'];

// Check if user exists and is not verified
$stmt = $conn->prepare("SELECT * FROM users WHERE email = ? AND is_verified = 0 AND auth_method = 'email'");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 1) {
    $user = $result->fetch_assoc();
    
    // Generate new verification token
    $verification_token = bin2hex(random_bytes(32));
    $token_expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));
    
    // Update user with new token
    $update_stmt = $conn->prepare("UPDATE users SET verification_token = ?, token_expiry = ? WHERE id = ?");
    $update_stmt->bind_param("ssi", $verification_token, $token_expiry, $user['id']);
    
    if ($update_stmt->execute()) {
        // Send verification email
        $verification_link = "http://" . $_SERVER['HTTP_HOST'] . "/verify_email.php?token=" . $verification_token;
        
        $to = $email;
        $subject = "Verify Your Email - Student Chatbot";
        $message = "Hello " . $user['name'] . ",\n\n";
        $message .= "You requested a new verification link. Please click the link below to verify your email address:\n\n";
        $message .= $verification_link . "\n\n";
        $message .= "This link will expire in 24 hours.\n\n";
        $message .= "If you did not request this, please ignore this email.\n\n";
        $message .= "Regards,\nStudent Chatbot Team";
        $headers = "From: noreply@studentchatbot.com";
        
        if (mail($to, $subject, $message, $headers)) {
            $_SESSION['success'] = "Verification email sent! Please check your inbox.";
            header("Location: login.html");
            exit();
        } else {
            $_SESSION['error'] = "Failed to send verification email. Please try again.";
            header("Location: login.html");
            exit();
        }
    } else {
        $_SESSION['error'] = "Failed to generate new verification link. Please try again.";
        header("Location: login.html");
        exit();
    }
    
    $update_stmt->close();
} else {
    // User not found or already verified
    $_SESSION['error'] = "Email not found or already verified";
    header("Location: login.html");
    exit();
}

$stmt->close();
$conn->close();
?>