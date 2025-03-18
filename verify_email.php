<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db_connect.php';
require_once 'functions.php';

// Add logging for debugging
function debug_log($message) {
    error_log(date('[Y-m-d H:i:s] ') . $message . "\n", 3, "verification_debug.log");
}

debug_log("Verification attempt started with full error reporting");

// Display detailed information about the process
echo "<!DOCTYPE html>
<html>
<head>
    <title>Email Verification</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; line-height: 1.6; }
        .container { max-width: 800px; margin: 0 auto; }
        .success { color: green; }
        .error { color: red; }
        .log { background: #f5f5f5; padding: 15px; border-radius: 5px; margin: 20px 0; }
        pre { white-space: pre-wrap; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>Email Verification Process</h1>
        <div class='log'>";

// Check database connection
if ($conn->connect_error) {
    debug_log("Database connection failed: " . $conn->connect_error);
    echo "<p class='error'>Database connection failed: " . $conn->connect_error . "</p>";
    echo "</div></div></body></html>";
    exit();
}

echo "<p>Database connection successful</p>";

// Check if token is provided
if (!isset($_GET['token']) || empty($_GET['token'])) {
    debug_log("No token provided in URL");
    echo "<p class='error'>No verification token provided</p>";
    echo "<p>Please use the link from your verification email</p>";
    echo "</div><p><a href='login.html'>Go to Login Page</a></p></div></body></html>";
    exit();
}

$token = $_GET['token'];
echo "<p>Processing token: " . htmlspecialchars(substr($token, 0, 10) . "...") . "</p>";
debug_log("Processing token: " . substr($token, 0, 10) . "...");

// First check if token exists without time constraint
$stmt = $conn->prepare("SELECT * FROM users WHERE verification_token = ?");
if (!$stmt) {
    debug_log("Prepare statement failed: " . $conn->error);
    echo "<p class='error'>Database query preparation failed: " . $conn->error . "</p>";
    echo "</div></div></body></html>";
    exit();
}

$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    debug_log("No user found with this token");
    echo "<p class='error'>No user account found with this verification token</p>";
    echo "<p>The token may be invalid or already used</p>";
    echo "</div><p><a href='resend_verification.php'>Request a new verification email</a></p></div></body></html>";
    $stmt->close();
    $conn->close();
    exit();
}

$user = $result->fetch_assoc();
echo "<p>Found user with ID: " . $user['id'] . "</p>";
echo "<p>Current verification status: " . ($user['is_verified'] ? "Verified" : "Not verified") . "</p>";
debug_log("User found with ID: " . $user['id'] . ", current verification status: " . $user['is_verified']);

// If user is already verified
if ($user['is_verified'] == 1) {
    debug_log("User is already verified");
    echo "<p class='success'>Your email is already verified!</p>";
    echo "</div><p><a href='login.html'>Go to Login Page</a></p></div></body></html>";
    $stmt->close();
    $conn->close();
    exit();
}

// Try to update user verification status with direct query first for debugging
$user_id = $user['id'];
echo "<p>Attempting to update verification status for user ID: " . $user_id . "</p>";

$direct_query = "UPDATE users SET is_verified = 1, verification_token = NULL, token_expiry = NULL WHERE id = $user_id";
$direct_result = $conn->query($direct_query);

if ($direct_result) {
    debug_log("Direct update query successful");
    echo "<p class='success'>Verification update successful!</p>";
} else {
    debug_log("Direct update query failed: " . $conn->error);
    echo "<p class='error'>Verification update failed: " . $conn->error . "</p>";
}

// Verify the change happened
$check_query = "SELECT is_verified FROM users WHERE id = $user_id";
$check_result = $conn->query($check_query);
$updated_user = $check_result->fetch_assoc();

if ($updated_user['is_verified'] == 1) {
    debug_log("Verification confirmed: is_verified is now 1");
    echo "<p class='success'>Verification confirmed in database</p>";
    
    // Set session success message for login page
    $_SESSION['success'] = "Email verified successfully! You can now log in.";
    
    echo "</div>
    <h2 class='success'>Email Verified Successfully!</h2>
    <p>Your account has been verified and you can now log in.</p>
    <p><a href='login.html'>Go to Login Page</a></p>
    </div></body></html>";
} else {
    debug_log("Verification failed: is_verified is still 0");
    echo "<p class='error'>Verification failed: Database shows account is still unverified</p>";
    echo "</div>
    <h2 class='error'>Verification Error</h2>
    <p>There was a problem verifying your account. Please try again or contact support.</p>
    <p><a href='resend_verification.php'>Request a new verification email</a></p>
    </div></body></html>";
}

$stmt->close();
$conn->close();
exit();
?>