<?php
session_start();
require_once 'db_connect.php';
require_once 'functions.php';

// Check if token is provided
if (!isset($_GET['token']) || empty($_GET['token'])) {
    $_SESSION['error'] = "Invalid password reset link";
    header("Location: login.html");
    exit();
}

$token = $_GET['token'];

// Check if token exists and is valid
$stmt = $conn->prepare("SELECT * FROM users WHERE reset_token = ? AND token_expiry > NOW()");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows != 1) {
    // Invalid or expired token
    $_SESSION['error'] = "Invalid or expired password reset link. Please request a new one.";
    header("Location: forgot_password.php");
    exit();
}

// Token is valid, show reset password form
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reset Password - Student Chatbot</title>
  <link rel="stylesheet" href="auth.css">
</head>
<body>
  <div class="auth-container">
    <div class="auth-form">
      <h1>Reset Password</h1>
      
      <?php if (isset($_SESSION['error'])): ?>
        <div class="alert error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
      <?php endif; ?>
      
      <form id="reset-password-form" action="reset_password_process.php" method="post">
        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
        
        <div class="form-group">
          <label for="password">New Password</label>
          <input type="password" id="password" name="password" required>
          <small class="password-requirements">
            Password must be at least 8 characters long with a mix of uppercase, lowercase, numbers, and special characters.
          </small>
        </div>
        
        <div class="form-group">
          <label for="confirm-password">Confirm New Password</label>
          <input type="password" id="confirm-password" name="confirm-password" required>
        </div>
        
        <button type="submit" class="btn btn-primary">Reset Password</button>
      </form>
      
      <p class="text-center">
        <a href="login.html">Back to Login</a>
      </p>
    </div>
  </div>
  
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const form = document.getElementById('reset-password-form');
      
      form.addEventListener('submit', function(e) {
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirm-password').value;
        
        // Client-side validation
        if (!password || !confirmPassword) {
          e.preventDefault();
          showAlert('Please fill in all fields');
          return;
        }
        
        // Password validation
        if (password.length < 8) {
          e.preventDefault();
          showAlert('Password must be at least 8 characters long');
          return;
        }
        
        // Password complexity check
        const passwordRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/;
        if (!passwordRegex.test(password)) {
          e.preventDefault();
          showAlert('Password must contain uppercase, lowercase, number, and special character');
          return;
        }
        
        // Check if passwords match
        if (password !== confirmPassword) {
          e.preventDefault();
          showAlert('Passwords do not match');
          return;
        }
      });
      
      function showAlert(message) {
        const alertDiv = document.createElement('div');
        alertDiv.className = 'alert error';
        alertDiv.textContent = message;
        
        const existingAlert = document.querySelector('.alert');
        if (existingAlert) {
          existingAlert.remove();
        }
        
        form.insertBefore(alertDiv, form.firstChild);
      }
    });
  </script>
</body>
</html>