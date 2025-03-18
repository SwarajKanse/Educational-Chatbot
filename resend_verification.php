<?php
session_start();
require_once 'db_connect.php';
require_once 'functions.php';

// Include the improved send_email function from signup_process.php if not already available
if (!function_exists('send_email')) {
    function send_email($to, $subject, $message, $from_name = "Student Chatbot", $from_email = "noreply@studentchatbot.com") {
        // Check if PHPMailer is already required/included
        if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            // If using Composer
            if (file_exists('vendor/autoload.php')) {
                require 'vendor/autoload.php';
            } else {
                // Direct inclusion
                require 'PHPMailer/src/Exception.php';
                require 'PHPMailer/src/PHPMailer.php';
                require 'PHPMailer/src/SMTP.php';
            }
        }

        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            
            // Server settings
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
            error_log("PHPMailer error: " . $e->getMessage());
            return false;
        }
    }
}

// Function to return JSON response
function json_response($success, $message) {
    header('Content-Type: application/json');
    echo json_encode(['success' => $success, 'message' => $message]);
    exit();
}

// Check if email is provided
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['email'])) {
    $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
    
    if (!$email) {
        json_response(false, "Invalid email address");
    }
    
    // Find user by email
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? AND is_verified = 0");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        json_response(false, "No pending verification found for this email");
    }
    
    $user = $result->fetch_assoc();
    
    // Check if there's an existing token or generate a new one
    $verification_token = $user['verification_token'];
    $token_expiry = $user['token_expiry'];
    
    // If token is expired or doesn't exist, generate a new one
    if (empty($verification_token) || strtotime($token_expiry) < time()) {
        $verification_token = bin2hex(random_bytes(32));
        $token_expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        // Update token in database
        $update_stmt = $conn->prepare("UPDATE users SET verification_token = ?, token_expiry = ? WHERE id = ?");
        $update_stmt->bind_param("ssi", $verification_token, $token_expiry, $user['id']);
        $update_stmt->execute();
        $update_stmt->close();
    }
    
    // Prepare verification email
    $verification_link = "http://" . $_SERVER['HTTP_HOST'] . "/direct_verify.php?token=" . $verification_token;
    
    $subject = "Verify Your Email - Student Chatbot";
    $message = "Hello " . $user['name'] . ",\n\n";
    $message .= "Please click the link below to verify your email address:\n\n";
    $message .= $verification_link . "\n\n";
    $message .= "This link will expire in 24 hours.\n\n";
    $message .= "If you did not sign up for an account, please ignore this email.\n\n";
    $message .= "Regards,\nStudent Chatbot Team";
    
    // Try to send email
    if (send_email($email, $subject, $message)) {
        json_response(true, "Verification email sent successfully. Please check your email.");
    } else {
        json_response(false, "Failed to send verification email. Please try again later.");
    }
    
} else {
    // Show the form if it's not a POST request
    // Removed include_once 'header.php' - we'll handle the entire page here
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resend Verification Email - Student Chatbot</title>
    <link rel="stylesheet" href="auth.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-form">
            <h1>Resend Verification Email</h1>
            <div id="alert-message" class="alert hidden"></div>
            
            <form id="resend-form" action="resend_verification.php" method="post">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required
                        value="<?php echo isset($_SESSION['pending_verification_email']) ? htmlspecialchars($_SESSION['pending_verification_email']) : ''; ?>">
                </div>
                <button type="submit" class="btn btn-primary">Resend Verification Email</button>
            </form>
            
            <p class="text-center">
                <a href="login.html">Back to Login</a>
            </p>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Show alert message
        function showAlert(message, type) {
            const alertElement = document.getElementById('alert-message');
            alertElement.textContent = message;
            alertElement.classList.remove('hidden', 'error', 'success');
            alertElement.classList.add(type);
            
            // Auto-hide after 5 seconds
            setTimeout(() => {
                alertElement.classList.add('hidden');
            }, 5000);
        }
        
        // Handle form submission
        const resendForm = document.getElementById('resend-form');
        if (resendForm) {
            resendForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const email = document.getElementById('email').value;
                
                // Client-side validation
                if (!email) {
                    showAlert('Please enter your email', 'error');
                    return;
                }
                
                // Show loading state
                showAlert('Sending verification email...', 'info');
                
                // Submit the form using fetch API
                fetch(resendForm.action, {
                    method: 'POST',
                    body: new FormData(resendForm)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert(data.message, 'success');
                    } else {
                        showAlert(data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error submitting form:', error);
                    showAlert('Network error. Please check your connection and try again.', 'error');
                });
            });
        }
    });
    </script>
</body>
</html>
<?php
}

$conn->close();
?>