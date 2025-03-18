<?php
// direct_verify.php - A simplified verification script with no dependencies
// Place this file in your project directory and access it directly with your token

// Display all errors
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Database connection
$host = "localhost"; // Change if needed
$username = "root";  // Change to your DB username
$password = "";      // Change to your DB password
$dbname = "student_chatbot"; // Change to your DB name

// Create connection
$conn = new mysqli($host, $username, $password, $dbname, 3307);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h1>Email Verification</h1>";

// Check if token is provided
if (!isset($_GET['token']) || empty($_GET['token'])) {
    die("<p style='color:red'>No verification token provided</p>");
}

$token = $_GET['token'];
echo "<p>Processing token: " . htmlspecialchars(substr($token, 0, 10) . "...") . "</p>";

// Find user by token
$sql = "SELECT * FROM users WHERE verification_token = '$token'";
$result = $conn->query($sql);

if ($result->num_rows == 0) {
    die("<p style='color:red'>No user found with this token</p>");
}

$user = $result->fetch_assoc();
echo "<p>Found user ID: " . $user['id'] . "</p>";
echo "<p>Current verification status: " . ($user['is_verified'] == 1 ? "Verified" : "Not verified") . "</p>";

// If already verified
if ($user['is_verified'] == 1) {
    echo "<p style='color:green'>Your email is already verified!</p>";
    echo "<p><a href='login.html'>Go to Login Page</a></p>";
    $conn->close();
    exit();
}

// Update user verification status - direct query
$user_id = $user['id'];
$update_sql = "UPDATE users SET is_verified = 1, verification_token = NULL, token_expiry = NULL WHERE id = $user_id";

if ($conn->query($update_sql) === TRUE) {
    echo "<p style='color:green'>Verification successful!</p>";
    
    // Verify the update worked
    $check_sql = "SELECT is_verified FROM users WHERE id = $user_id";
    $check_result = $conn->query($check_sql);
    $updated_user = $check_result->fetch_assoc();
    
    if ($updated_user['is_verified'] == 1) {
        echo "<p style='color:green'>Database update confirmed. You can now log in.</p>";
    } else {
        echo "<p style='color:red'>Warning: Database says account is still unverified.</p>";
    }
    
    echo "<p><a href='login.html'>Go to Login Page</a></p>";
} else {
    echo "<p style='color:red'>Error updating record: " . $conn->error . "</p>";
}

$conn->close();
?>