<?php
session_start();
require_once 'db_connect.php';
require_once 'functions.php';

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm-password'];
    
    // Basic validation
    if (empty($name) || empty($email) || empty($password) || empty($confirm_password)) {
        $_SESSION['error'] = "Please fill in all fields";
        header("Location: signup.html");
        exit();
    }
    
    // Check if passwords match
    if ($password !== $confirm_password) {
        $_SESSION['error'] = "Passwords do not match";
        header("Location: signup.html");
        exit();
    }
    
    // Password complexity validation
    if (strlen($password) < 8 || !preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/', $password)) {
        $_SESSION['error'] = "Password must be at least 8 characters with uppercase, lowercase, number, and special character";
        header("Location: signup.html");
        exit();
    }
    
    // Check if email already exists
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $_SESSION['error'] = "Email already exists";
        header("Location: signup.html");
        exit();
    }
    
    // Generate verification token
    $verification_token = bin2hex(random_bytes(32));
    $token_expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));
    
    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert user into database
    $stmt = $conn->prepare("INSERT INTO users (name, email, password, auth_method, verification_token, token_expiry, created_at) VALUES (?, ?, ?, 'email', ?, ?, NOW())");
    $stmt->bind_param("sssss", $name, $email, $hashed_password, $verification_token, $token_expiry);
    
    if ($stmt->execute()) {
        // Send verification email
        $verification_link = "http://" . $_SERVER['HTTP_HOST'] . "/verify_email.php?token=" . $verification_token;
        
        $to = $email;
        $subject = "Verify Your Email - Student Chatbot";
        $message = "Hello " . $name . ",\n\n";
        $message .= "Thank you for signing up! Please click the link below to verify your email address:\n\n";
        $message .= $verification_link . "\n\n";
        $message .= "This link will expire in 24 hours.\n\n";
        $message .= "If you did not sign up for an account, please ignore this email.\n\n";
        $message .= "Regards,\nStudent Chatbot Team";
        $headers = "From: noreply@studentchatbot.com";
        
        if (mail($to, $subject, $message, $headers)) {
            $_SESSION['success'] = "Registration successful! Please check your email to verify your account.";
            header("Location: login.html");
            exit();
        } else {
            // Email sending failed
            $_SESSION['error'] = "Failed to send verification email. Please try again.";
            
            // Delete the user since verification email failed
            $stmt = $conn->prepare("DELETE FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            
            header("Location: signup.html");
            exit();
        }
    } else {
        // Registration failed
        $_SESSION['error'] = "Registration failed. Please try again.";
        header("Location: signup.html");
        exit();
    }
    
    $stmt->close();
} else {
    // Not a POST request
    header("Location: signup.html");
    exit();
}

$conn->close();
?>