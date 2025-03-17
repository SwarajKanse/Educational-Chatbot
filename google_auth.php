<?php
session_start();
require_once 'db_connect.php';
require_once 'functions.php';

// Get JSON data from POST request
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

// Check if data is valid
if (!isset($data['uid']) || !isset($data['email']) || !isset($data['name']) || !isset($data['action'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit();
}

$uid = $data['uid'];
$email = $data['email'];
$name = $data['name'];
$photo_url = $data['photoURL'] ?? '';
$action = $data['action'];

// Check if user exists
$stmt = $conn->prepare("SELECT * FROM users WHERE firebase_uid = ? OR email = ?");
$stmt->bind_param("ss", $uid, $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // User exists
    $user = $result->fetch_assoc();
    
    // Update user data if needed
    if ($user['firebase_uid'] != $uid || $user['name'] != $name || $user['photo_url'] != $photo_url) {
        $update_stmt = $conn->prepare("UPDATE users SET firebase_uid = ?, name = ?, photo_url = ?, last_login = NOW() WHERE id = ?");
        $update_stmt->bind_param("sssi", $uid, $name, $photo_url, $user['id']);
        $update_stmt->execute();
        $update_stmt->close();
    }
    
    // Set session data
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $name;
    $_SESSION['user_email'] = $email;
    $_SESSION['logged_in'] = true;
    
    echo json_encode(['success' => true]);
    exit();
} else {
    // User doesn't exist
    if ($action === 'signup') {
        // Create new user
        $stmt = $conn->prepare("INSERT INTO users (firebase_uid, name, email, photo_url, auth_method, is_verified, created_at, last_login) VALUES (?, ?, ?, ?, 'google', 1, NOW(), NOW())");
        $stmt->bind_param("ssss", $uid, $name, $email, $photo_url);
        
        if ($stmt->execute()) {
            // Set session data
            $_SESSION['user_id'] = $conn->insert_id;
            $_SESSION['user_name'] = $name;
            $_SESSION['user_email'] = $email;
            $_SESSION['logged_in'] = true;
            
            echo json_encode(['success' => true]);
            exit();
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to create account']);
            exit();
        }
    } else {
        // Login attempt for non-existent user
        echo json_encode(['success' => false, 'message' => 'No account found with this email']);
        exit();
    }
}

$stmt->close();
$conn->close();
?>