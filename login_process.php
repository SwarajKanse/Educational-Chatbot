<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'db_connect.php';
require_once 'functions.php';

// Check if request wants JSON response
function wants_json() {
    return (isset($_SERVER['HTTP_ACCEPT']) && 
           (strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)) ||
           isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

// JSON response helper
function json_response($success, $message, $redirect = null) {
    header('Content-Type: application/json');
    $response = [
        'success' => $success,
        'message' => $message
    ];
    
    if ($redirect) {
        $response['redirect'] = $redirect;
    }
    
    echo json_encode($response);
    exit();
}

// Regular response helper (with redirect)
function regular_response($success, $message, $redirect) {
    if ($success) {
        $_SESSION['success'] = $message;
    } else {
        $_SESSION['error'] = $message;
    }
    header("Location: $redirect");
    exit();
}

// Response based on request type
function respond($success, $message, $redirect) {
    if (wants_json()) {
        json_response($success, $message, $redirect);
    } else {
        regular_response($success, $message, $redirect);
    }
}

// Log to file for debugging
function debug_log($message) {
    error_log(date('[Y-m-d H:i:s] ') . $message . "\n", 3, "login_debug.log");
}

// Start the login process
debug_log("Login process started");

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    debug_log("POST request received");
    
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    debug_log("Login attempt for email: " . $email);
    
    // Basic validation
    if (empty($email) || empty($password)) {
        debug_log("Validation failed: empty fields");
        respond(false, "Please fill in all fields", "login.html");
    }
    
    // Check if email exists
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? AND auth_method = 'email'");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        debug_log("User found with ID: " . $user['id']);
        
        // Verify password
        if (password_verify($password, $user['password'])) {
            debug_log("Password verified successfully");
            
            // Check if email is verified
            if ($user['is_verified'] == 1) {
                debug_log("User is verified, logging in");
                
                // Login successful
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['logged_in'] = true;
                
                // Update last login time
                $stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $stmt->bind_param("i", $user['id']);
                $stmt->execute();
                
                debug_log("User logged in and redirecting to index.php");
                
                // Redirect to dashboard/home
                respond(true, "Login successful!", "index.php");
            } else {
                debug_log("Email not verified");
                
                // Email not verified
                $resend_link = "<a href='resend_verification.php?email=" . urlencode($email) . "'>Resend verification email</a>";
                respond(false, "Please verify your email before logging in. " . $resend_link, "login.html");
            }
        } else {
            debug_log("Invalid password");
            
            // Invalid password
            respond(false, "Invalid email or password", "login.html");
        }
    } else {
        debug_log("User not found");
        
        // User not found
        respond(false, "Invalid email or password", "login.html");
    }
    
    $stmt->close();
} else {
    debug_log("Not a POST request, redirecting to login page");
    
    // Not a POST request
    respond(false, "Invalid request method", "login.html");
}

$conn->close();
?>