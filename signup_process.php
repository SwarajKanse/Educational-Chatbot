<?php
// Keep minimal error reporting for production
ini_set('display_errors', 0); // Don't display errors in the response
error_reporting(E_ALL); // Still report all types of errors to log

session_start();
require_once 'db_connect.php';
require_once 'functions.php';

// Function to log errors
function log_error($message) {
    error_log($message, 3, "signup_errors.log");
}

// Function to return JSON response
function json_response($success, $message) {
    header('Content-Type: application/json');
    echo json_encode(['success' => $success, 'message' => $message]);
    exit();
}

// Improved function to send emails using PHPMailer
function send_email($to, $subject, $message, $from_name = "Student Chatbot", $from_email = "noreply@studentchatbot.com") {
    // Check if PHPMailer is already required/included
    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        // If using Composer
        if (file_exists('vendor/autoload.php')) {
            require 'vendor/autoload.php';
        } else {
            // Direct inclusion - you need to download PHPMailer first
            require 'PHPMailer/src/Exception.php';
            require 'PHPMailer/src/PHPMailer.php';
            require 'PHPMailer/src/SMTP.php';
        }
    }

    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        // Server settings
        // $mail->SMTPDebug = 2; // Uncomment for debugging
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com'; // Change to your SMTP server
        $mail->SMTPAuth   = true;
        $mail->Username   = 'swarajkanse2@gmail.com'; // Change to your email username
        $mail->Password   = 'ovgb yzyc oibi folz'; // Change to your email password
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Recipients
        $mail->setFrom($from_email, $from_name);
        $mail->addAddress($to);

        // Content
        $mail->isHTML(false);
        $mail->Subject = $subject;
        $mail->Body    = $message;

        return $mail->send();
    } catch (Exception $e) {
        log_error("PHPMailer error: " . $e->getMessage());
        return false;
    }
}

try {
    // Check if form is submitted
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $name = isset($_POST['name']) ? trim($_POST['name']) : '';
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        $confirm_password = isset($_POST['confirm-password']) ? $_POST['confirm-password'] : '';
        
        // Basic validation
        if (empty($name) || empty($email) || empty($password) || empty($confirm_password)) {
            json_response(false, "Please fill in all fields");
        }
        
        // Check if passwords match
        if ($password !== $confirm_password) {
            json_response(false, "Passwords do not match");
        }
        
        // Password complexity validation
        if (strlen($password) < 8 || !preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/', $password)) {
            json_response(false, "Password must be at least 8 characters with uppercase, lowercase, number, and special character");
        }
        
        // Check if email already exists
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            json_response(false, "Email already exists");
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
            // Prepare verification email
            $verification_link = "http://" . $_SERVER['HTTP_HOST'] . "/WBP_Programs/Chatbot/direct_verify.php?token=" . $verification_token;
            
            $subject = "Verify Your Email - Student Chatbot";
            $message = "Hello " . $name . ",\n\n";
            $message .= "Thank you for signing up! Please click the link below to verify your email address:\n\n";
            $message .= $verification_link . "\n\n";
            $message .= "This link will expire in 24 hours.\n\n";
            $message .= "If you did not sign up for an account, please ignore this email.\n\n";
            $message .= "Regards,\nStudent Chatbot Team";
            
            // Try to send email using our improved function
            if (send_email($email, $subject, $message)) {
                json_response(true, "Registration successful! Please check your email to verify your account.");
            } else {
                log_error("Failed to send verification email to: " . $email);
                
                // Alternative: Create a resend verification page and redirect there
                $_SESSION['pending_verification_email'] = $email;
                json_response(true, "Account created, but we couldn't send the verification email. Please use the 'Resend Verification Email' option on the login page.");
            }
        } else {
            // Registration failed
            log_error("Database insert failed: " . $conn->error);
            json_response(false, "Registration failed. Please try again.");
        }
        
        $stmt->close();
    } else {
        // Not a POST request
        json_response(false, "Invalid request method");
    }
} catch (Exception $e) {
    log_error("Exception caught: " . $e->getMessage());
    json_response(false, "An error occurred. Please try again later.");
}

$conn->close();
?>