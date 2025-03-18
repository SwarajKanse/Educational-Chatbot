<?php
// direct_verify.php - Minimal UI for email verification

// Database connection
$host = "localhost"; 
$username = "root";  
$password = "";      
$dbname = "student_chatbot"; 

// Create connection
$conn = new mysqli($host, $username, $password, $dbname, 3307);

// Check connection
if ($conn->connect_error) {
    $message = "Connection failed: " . $conn->connect_error;
    $status = false;
} else {
    // Process verification
    if (!isset($_GET['token']) || empty($_GET['token'])) {
        $message = "No verification token provided";
        $status = false;
    } else {
        $token = $_GET['token'];
        
        // Find user by token
        $sql = "SELECT * FROM users WHERE verification_token = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            $message = "Invalid verification token";
            $status = false;
        } else {
            $user = $result->fetch_assoc();
            
            // If already verified
            if ($user['is_verified'] == 1) {
                $message = "Your email is already verified";
                $status = true;
            } else {
                // Update user verification status
                $user_id = $user['id'];
                $update_sql = "UPDATE users SET is_verified = 1, verification_token = NULL, token_expiry = NULL WHERE id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("i", $user_id);
                
                if ($update_stmt->execute()) {
                    $message = "Your email has been successfully verified";
                    $status = true;
                } else {
                    $message = "Error updating record";
                    $status = false;
                }
                $update_stmt->close();
            }
        }
        $stmt->close();
    }
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fa;
            margin: 0;
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
        }
        
        .card {
            background: white;
            max-width: 400px;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        h1 {
            color: #4285f4;
            margin-top: 0;
        }
        
        .message {
            margin: 20px 0;
            line-height: 1.5;
        }
        
        .success {
            color: #34a853;
        }
        
        .error {
            color: #ea4335;
        }
        
        .btn {
            display: inline-block;
            background-color: #4285f4;
            color: white;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 4px;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>Email Verification</h1>
        
        <div class="message <?php echo $status ? 'success' : 'error'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
        
        <a href="login.html" class="btn">Go to Login</a>
    </div>
</body>
</html>