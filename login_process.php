<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'db_connect.php';
require_once 'functions.php';

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    // Basic validation
    if (empty($email) || empty($password)) {
        $_SESSION['error'] = "Please fill in all fields";
        header("Location: login.html");
        exit();
    }
    
    // Check if email exists
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? AND auth_method = 'email'");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        
        // Verify password
        if (password_verify($password, $user['password'])) {
            // Check if email is verified
            if ($user['is_verified'] == 1) {
                // Login successful
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['logged_in'] = true;
                
                // Update last login time
                $stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $stmt->bind_param("i", $user['id']);
                $stmt->execute();
                
                // Redirect to dashboard/home
                header("Location: index.php");
                exit();
            } else {
                // Email not verified
                $_SESSION['error'] = "Please verify your email before logging in. <a href='resend_verification.php?email=" . urlencode($email) . "'>Resend verification email</a>";
                header("Location: login.html");
                exit();
            }
        } else {
            // Invalid password
            $_SESSION['error'] = "Invalid email or password";
            header("Location: login.html");
            exit();
        }
    } else {
        // User not found
        $_SESSION['error'] = "Invalid email or password";
        header("Location: login.html");
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