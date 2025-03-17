<?php
session_start();
require_once 'db_connect.php';
require_once 'functions.php';

// Check if user is logged in
if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Get conversation history from database
$stmt = $conn->prepare("SELECT content, is_bot, timestamp FROM conversation_history WHERE user_id = ? ORDER BY timestamp ASC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$messages = [];
while ($row = $result->fetch_assoc()) {
    $messages[] = [
        'content' => $row['content'],
        'is_bot' => (bool)$row['is_bot'],
        'timestamp' => $row['timestamp']
    ];
}

echo json_encode(['success' => true, 'messages' => $messages]);
$stmt->close();
?>