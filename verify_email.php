<?php
session_start();
require_once 'db_connect.php';
require_once 'functions.php';

// Check if token is provided
if (!isset($_GET['token']) || empty($_GET['token'])) {
    $_SESSION['error'] = "Invalid verification link";
    header("Location: login.html");
    exit();
}

$token = $_GET['token'];

// Check if token exists and is valid
$stmt = $conn->prepare("SELECT * FROM users WHERE verification_token = ? AND token_expiry > NOW()");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 1) {
    $user = $result->fetch_assoc();
    
    // Update user as verified
    $update_stmt = $conn->prepare("UPDATE users SET is_verified = 1, verification_token = NULL, token_expiry = NULL WHERE id = ?");
    $update_stmt->bind_param("i", $user['id']);
    
    if ($update_stmt->execute()) {
        $_SESSION['success'] = "Email verified successfully! You can now log in.";
        header("Location: login.html");
        exit();
    } else {
        $_SESSION['error'] = "Failed to verify email. Please try again.";
        header("Location: login.html");
        exit();
    }
    
    $update_stmt->close();
} else {
    // Invalid or expired token
    $_SESSION['error'] = "Invalid or expired verification link. Please request a new one.";
    header("Location: login.html");
    exit();
}

$stmt->close();
$conn->close();
?>