<?php
session_start();
require_once 'db_connect.php';
require_once 'functions.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Forgot Password - Student Chatbot</title>
  <link rel="stylesheet" href="auth.css">
</head>
<body>
  <div class="auth-container">
    <div class="auth-form">
      <h1>Reset Password</h1>
      
      <?php if (isset($_SESSION['error'])): ?>
        <div class="alert error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
      <?php endif; ?>
      
      <?php if (isset($_SESSION['success'])): ?>
        <div class="alert success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
      <?php endif; ?>
      
      <form id="forgot-password-form" action="forgot_password_process.php" method="post">
        <div class="form-group">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" required>
        </div>
        <button type="submit" class="btn btn-primary">Send Reset Link</button>
      </form>
      
      <p class="text-center">
        <a href="login.html">Back to Login</a>
      </p>
    </div>
  </div>
  
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const form = document.getElementById('forgot-password-form');
      
      form.addEventListener('submit', function(e) {
        const email = document.getElementById('email').value;
        
        if (!email) {
          e.preventDefault();
          
          const alertDiv = document.createElement('div');
          alertDiv.className = 'alert error';
          alertDiv.textContent = 'Please enter your email address';
          
          const existingAlert = document.querySelector('.alert');
          if (existingAlert) {
            existingAlert.remove();
          }
          
          form.insertBefore(alertDiv, form.firstChild);
        }
      });
    });
  </script>
</body>
</html>