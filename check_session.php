<?php
session_start();
require_once 'functions.php';

// Check if user is logged in
$response = array(
    'logged_in' => is_logged_in(),
    'user_id' => $_SESSION['user_id'] ?? null,
    'user_email' => $_SESSION['user_email'] ?? null
);

header('Content-Type: application/json');
echo json_encode($response);
?>