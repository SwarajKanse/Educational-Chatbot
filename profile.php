<?php
session_start();
require_once 'db_connect.php'; // This includes your connection as $conn
require_once 'functions.php';

// Check if user is logged in
require_login();

// Get user data
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$user_email = $_SESSION['user_email'];

// Handle form submission
$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $current_password = isset($_POST['current_password']) ? $_POST['current_password'] : '';
    $new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';
    $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
    
    // Validate inputs
    if (empty($new_name)) {
        $message = 'Name cannot be empty';
    } else {
        // Use the existing $conn from db_connect.php
        
        // Update name
        $update_name_query = "UPDATE users SET name = ? WHERE id = ?";
        $stmt = $conn->prepare($update_name_query);
        $stmt->bind_param("si", $new_name, $user_id);
        $stmt->execute();
        
        // Update session
        $_SESSION['user_name'] = $new_name;
        $user_name = $new_name;
        $success = true;
        $message = 'Profile updated successfully';
        
        // Check if password change is requested
        if (!empty($current_password) && !empty($new_password)) {
            // Verify current password
            $password_query = "SELECT password FROM users WHERE id = ?";
            $stmt = $conn->prepare($password_query);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            
            // Verify password
            if (password_verify($current_password, $user['password'])) {
                // Check if passwords match
                if ($new_password !== $confirm_password) {
                    $success = false;
                    $message = 'New passwords do not match';
                }
                // Password complexity validation - SAME AS SIGNUP PROCESS
                elseif (strlen($new_password) < 8 || !preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/', $new_password)) {
                    $success = false;
                    $message = 'Password must be at least 8 characters with uppercase, lowercase, number, and special character';
                } else {
                    // Update password
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $update_password_query = "UPDATE users SET password = ? WHERE id = ?";
                    $stmt = $conn->prepare($update_password_query);
                    $stmt->bind_param("si", $hashed_password, $user_id);
                    $stmt->execute();
                    
                    $success = true;
                    $message = 'Profile and password updated successfully';
                }
            } else {
                $success = false;
                $message = 'Current password is incorrect';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Profile Settings - Student Chatbot</title>
  <link rel="stylesheet" href="chat.css">
  <style>
    body {
      display: block;
      padding: 20px;
    }
    
    .profile-container {
      max-width: 600px;
      margin: 20px auto;
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 5px 25px rgba(0, 0, 0, 0.1);
      overflow: hidden;
    }
    
    .profile-header {
      background-color: #4285f4;
      color: white;
      padding: 20px;
      text-align: center;
    }
    
    .profile-header h1 {
      margin: 0;
      font-size: 24px;
    }
    
    .profile-content {
      padding: 20px;
    }
    
    .form-group {
      margin-bottom: 20px;
    }
    
    label {
      display: block;
      margin-bottom: 8px;
      font-weight: 500;
    }
    
    input[type="text"],
    input[type="email"],
    input[type="password"] {
      width: 100%;
      padding: 10px;
      border: 1px solid #e0e5ee;
      border-radius: 6px;
      font-size: 15px;
      box-sizing: border-box;
    }
    
    input[type="email"]:disabled {
      background-color: #f5f7fa;
    }
    
    .divider {
      margin: 30px 0;
      border-top: 1px solid #e0e5ee;
      position: relative;
    }
    
    .divider-text {
      position: absolute;
      top: -12px;
      left: 50%;
      transform: translateX(-50%);
      background: white;
      padding: 0 10px;
      color: #666;
      font-size: 14px;
    }
    
    .btn-container {
      text-align: center;
      margin-top: 30px;
    }
    
    .btn-save {
      background-color: #4285f4;
      color: white;
      border: none;
      padding: 12px 30px;
      border-radius: 6px;
      font-size: 16px;
      cursor: pointer;
      transition: background-color 0.2s;
    }
    
    .btn-save:hover {
      background-color: #3367d6;
    }
    
    .btn-back {
      color: #4285f4;
      background: none;
      border: none;
      cursor: pointer;
      margin-top: 15px;
      font-size: 14px;
      text-decoration: underline;
    }
    
    .alert {
      padding: 10px 15px;
      border-radius: 6px;
      margin-bottom: 20px;
    }
    
    .alert-success {
      background-color: #d4edda;
      color: #155724;
      border: 1px solid #c3e6cb;
    }
    
    .alert-danger {
      background-color: #f8d7da;
      color: #721c24;
      border: 1px solid #f5c6cb;
    }
    
    .password-requirements {
      font-size: 13px;
      color: #6c757d;
      margin-top: 5px;
    }
  </style>
</head>
<body>
  <div class="profile-container">
    <div class="profile-header">
      <h1>Profile Settings</h1>
    </div>
    
    <div class="profile-content">
      <?php if (!empty($message)): ?>
        <div class="alert <?php echo $success ? 'alert-success' : 'alert-danger'; ?>">
          <?php echo htmlspecialchars($message); ?>
        </div>
      <?php endif; ?>
      
      <form method="POST" action="profile.php">
        <div class="form-group">
          <label for="name">Name</label>
          <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($user_name); ?>" required>
        </div>
        
        <div class="form-group">
          <label for="email">Email Address</label>
          <input type="email" id="email" value="<?php echo htmlspecialchars($user_email); ?>" disabled>
          <small>Email address cannot be changed</small>
        </div>
        
        <div class="divider">
          <span class="divider-text">Change Password</span>
        </div>
        
        <div class="form-group">
          <label for="current_password">Current Password</label>
          <input type="password" id="current_password" name="current_password">
        </div>
        
        <div class="form-group">
          <label for="new_password">New Password</label>
          <input type="password" id="new_password" name="new_password">
          <div class="password-requirements">
            Password must be at least 8 characters and include uppercase, lowercase, 
            number, and special character
          </div>
        </div>
        
        <div class="form-group">
          <label for="confirm_password">Confirm New Password</label>
          <input type="password" id="confirm_password" name="confirm_password">
        </div>
        
        <div class="btn-container">
          <button type="submit" class="btn-save">Save Changes</button>
          <br>
          <a href="index.php" class="btn-back">Back to Chat</a>
        </div>
      </form>
    </div>
  </div>
</body>
</html>