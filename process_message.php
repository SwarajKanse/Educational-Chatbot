<?php
session_start();
require_once 'db_connect.php';
require_once 'functions.php';

// Check if user is logged in
if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit();
}

// Get user ID
$user_id = $_SESSION['user_id'];

// Get JSON data from POST request
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

// Check if message is provided
if (!isset($data['message']) || empty($data['message'])) {
    echo json_encode(['success' => false, 'message' => 'No message provided']);
    exit();
}

$user_message = sanitize_input($data['message']);

// Save user message to database
$stmt = $conn->prepare("INSERT INTO conversation_history (user_id, content, is_bot, timestamp) VALUES (?, ?, 0, NOW())");
$stmt->bind_param("is", $user_id, $user_message);
$stmt->execute();
$stmt->close();

// This is where you would send the user message to your chatbot API
// For now, we'll use a simple response based on keywords

$bot_response = generateBotResponse($user_message);

// Save bot response to database
$stmt = $conn->prepare("INSERT INTO conversation_history (user_id, content, is_bot, timestamp) VALUES (?, ?, 1, NOW())");
$stmt->bind_param("is", $user_id, $bot_response);
$stmt->execute();
$stmt->close();

echo json_encode(['success' => true, 'response' => $bot_response]);

/**
 * Generate a simple chatbot response based on keywords
 * In a real application, this would be replaced with an actual API call to your chatbot service
 */
function generateBotResponse($message) {
    $message = strtolower($message);
    
    if (strpos($message, 'hello') !== false || strpos($message, 'hi') !== false) {
        return "Hello! How can I help you today?";
    }
    
    if (strpos($message, 'help') !== false) {
        return "I'm here to assist you with your questions. What would you like to know?";
    }
    
    if (strpos($message, 'course') !== false || strpos($message, 'class') !== false) {
        return "We offer various courses on different subjects. Could you specify which subject you're interested in?";
    }
    
    if (strpos($message, 'exam') !== false || strpos($message, 'test') !== false) {
        return "Exams are an important part of the learning process. Do you have any specific questions about upcoming exams?";
    }
    
    if (strpos($message, 'assignment') !== false || strpos($message, 'homework') !== false) {
        return "I can help you understand your assignments better. What specifically are you struggling with?";
    }
    
    // Default response
    return "I understand you're asking about '" . htmlspecialchars($message) . "'. Could you provide more details so I can better assist you?";
}
?>