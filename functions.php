<?php
// Function to validate email
function is_valid_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Function to validate password strength
function is_strong_password($password) {
    // At least 8 characters with uppercase, lowercase, number, and special character
    return strlen($password) >= 8 && 
           preg_match('/[A-Z]/', $password) && 
           preg_match('/[a-z]/', $password) && 
           preg_match('/[0-9]/', $password) && 
           preg_match('/[^A-Za-z0-9]/', $password);
}

// Function to check if user is logged in
function is_logged_in() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

// Function to redirect if not logged in
function require_login() {
    if (!is_logged_in()) {
        $_SESSION['error'] = "Please log in to access this page";
        header("Location: login.html");
        exit();
    }
}

// Function to clear user conversation history
function clear_conversation_history($user_id) {
    global $conn;
    
    // Delete from conversation history
    $stmt = $conn->prepare("DELETE FROM conversation_history WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
    
    // Optional: Delete from vector database
    // This depends on how you're storing vector data
    // You might need to make an API call to your vector database service
    
    return true;
}

// Function to sanitize input
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Function to generate a random string
function generate_random_string($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $random_string = '';
    for ($i = 0; $i < $length; $i++) {
        $random_string .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $random_string;
}
?>