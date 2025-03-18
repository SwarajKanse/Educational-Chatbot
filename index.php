<?php
session_start();
require_once 'db_connect.php';
require_once 'functions.php';

// Check if user is logged in
require_login();

// Get user data
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$user_email = $_SESSION['user_email'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Student Chatbot</title>
  <link rel="stylesheet" href="chat.css">
  <style>
    /* Header styling */
    .header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 0 20px;
      background-color: #fff;
      box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
      height: 60px;
      width: 100%;
      position: fixed;
      top: 0;
      left: 0;
      z-index: 1000;
    }
    
    .logo {
      font-weight: bold;
      font-size: 18px;
      color: #4285f4;
    }
    
    /* Action buttons styling */
    .action-buttons {
      display: flex;
      gap: 15px;
    }
    
    .btn-new-chat {
      background-color: #4285f4;
      color: white;
      border: none;
      padding: 8px 16px;
      border-radius: 4px;
      cursor: pointer;
      font-weight: 500;
      transition: background-color 0.2s;
    }
    
    .btn-new-chat:hover {
      background-color: #3367d6;
    }
    
    /* Theme toggle styling */
    .theme-toggle {
      display: flex;
      align-items: center;
      cursor: pointer;
      user-select: none;
    }
    
    .toggle-switch {
      position: relative;
      display: inline-block;
      width: 40px;
      height: 20px;
      margin-left: 8px;
    }
    
    .toggle-switch input {
      opacity: 0;
      width: 0;
      height: 0;
    }
    
    .slider {
      position: absolute;
      cursor: pointer;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background-color: #ccc;
      transition: .4s;
      border-radius: 20px;
    }
    
    .slider:before {
      position: absolute;
      content: "";
      height: 16px;
      width: 16px;
      left: 2px;
      bottom: 2px;
      background-color: white;
      transition: .4s;
      border-radius: 50%;
    }
    
    input:checked + .slider {
      background-color: #4285f4;
    }
    
    input:checked + .slider:before {
      transform: translateX(20px);
    }
    
    /* User menu styling */
    .user-menu {
      position: relative;
      display: inline-block;
    }
    
    .user-info {
      display: flex;
      align-items: center;
      cursor: pointer;
      padding: 8px;
      border-radius: 4px;
    }
    
    .user-info:hover {
      background-color: #f5f7fa;
    }
    
    .user-avatar {
      width: 32px;
      height: 32px;
      border-radius: 50%;
      margin-right: 8px;
      background-color: #4285f4;
      color: white;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: bold;
    }
    
    .dropdown-menu {
      position: absolute;
      right: 0;
      top: 100%;
      background-color: #fff;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
      border-radius: 4px;
      min-width: 200px;
      display: none;
      z-index: 100;
    }
    
    .dropdown-menu.show {
      display: block;
    }
    
    .dropdown-item {
      padding: 10px 15px;
      display: block;
      color: #333;
      text-decoration: none;
    }
    
    .dropdown-item:hover {
      background-color: #f5f7fa;
    }
    
    /* Adjust chat container for fixed header */
    body {
      padding-top: 60px;
    }
    
    .chat-container {
      height: calc(85vh - 60px);
    }
    
    /* Dark mode styles */
    body.dark-mode {
      background-color: #1a1a1a;
      color: #f5f5f5;
    }
    
    body.dark-mode .header {
      background-color: #2a2a2a;
      box-shadow: 0 2px 5px rgba(0, 0, 0, 0.3);
    }
    
    body.dark-mode .logo {
      color: #5c9bff;
    }
    
    body.dark-mode .user-info:hover {
      background-color: #3a3a3a;
    }
    
    body.dark-mode .dropdown-menu {
      background-color: #2a2a2a;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
    }
    
    body.dark-mode .dropdown-item {
      color: #f5f5f5;
    }
    
    body.dark-mode .dropdown-item:hover {
      background-color: #3a3a3a;
    }
    
    body.dark-mode .chat-container {
      background-color: #2a2a2a;
    }
    
    body.dark-mode .chat-box {
      background-color: #2a2a2a;
      background-image: linear-gradient(rgba(60, 60, 60, 0.5) 1px, transparent 1px);
    }
    
    body.dark-mode .message.bot .text {
      background-color: #3a3a3a;
      color: #f5f5f5;
    }
    
    body.dark-mode #chat-input {
      background-color: #3a3a3a;
      color: #f5f5f5;
      border-color: #444;
    }
    
    body.dark-mode #chat-form {
      background-color: #2a2a2a;
      border-top: 1px solid #444;
    }
  </style>
</head>
<body>
  <div class="header">
    <div class="action-buttons">
      <button id="new-chat-btn" class="btn-new-chat">New Chat</button>
    </div>
    
    <div class="theme-toggle">
      Light/Dark
      <label class="toggle-switch">
        <input type="checkbox" id="theme-toggle">
        <span class="slider"></span>
      </label>
    </div>
    
    <div class="user-menu">
      <div class="user-info" id="user-dropdown-toggle">
        <div class="user-avatar">
          <?php echo substr($user_name, 0, 1); ?>
        </div>
        <span><?php echo htmlspecialchars($user_name); ?></span>
      </div>
      <div class="dropdown-menu" id="user-dropdown">
        <a href="profile.php" class="dropdown-item">Profile Settings</a>
        <a href="logout.php" class="dropdown-item">Logout</a>
      </div>
    </div>
  </div>
  
  <div class="chat-container">
    <div id="chat-box" class="chat-box"></div>
    <form id="chat-form">
      <input type="text" id="chat-input" placeholder="Type your message..." autocomplete="off">
      <button type="submit">Send</button>
    </form>
  </div>
  
  <script>
    // User dropdown toggle
    document.getElementById('user-dropdown-toggle').addEventListener('click', function() {
      document.getElementById('user-dropdown').classList.toggle('show');
    });
    
    // Close dropdown when clicking outside
    window.addEventListener('click', function(event) {
      if (!event.target.closest('#user-dropdown-toggle')) {
        document.getElementById('user-dropdown').classList.remove('show');
      }
    });
    
    // Theme toggle functionality
    const themeToggle = document.getElementById('theme-toggle');
    
    // Check for saved theme preference or default to light
    if (localStorage.getItem('dark-mode') === 'true') {
      document.body.classList.add('dark-mode');
      themeToggle.checked = true;
    }
    
    // Listen for toggle changes
    themeToggle.addEventListener('change', function() {
      if (this.checked) {
        document.body.classList.add('dark-mode');
        localStorage.setItem('dark-mode', 'true');
      } else {
        document.body.classList.remove('dark-mode');
        localStorage.setItem('dark-mode', 'false');
      }
    });
    
    // New chat button functionality
    document.getElementById('new-chat-btn').addEventListener('click', function() {
      if (confirm("Are you sure you want to start a new chat? This will clear all your current conversation history.")) {
        // Send request to clear chat history
        fetch('clear_chat.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          }
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            // Clear the chat UI
            document.getElementById('chat-box').innerHTML = '';
            // Display welcome message
            addBotMessage("Hello! How can I help you today?");
          } else {
            alert("Failed to clear chat history. Please try again.");
          }
        })
        .catch(error => {
          console.error('Error:', error);
          alert("An error occurred. Please try again.");
        });
      }
    });
    
    // Function to add bot message to UI
    function addBotMessage(message) {
      const chatBox = document.getElementById('chat-box');
      const messageDiv = document.createElement('div');
      messageDiv.classList.add('message', 'bot');
      
      const textDiv = document.createElement('div');
      textDiv.classList.add('text');
      textDiv.textContent = message;
      
      messageDiv.appendChild(textDiv);
      chatBox.appendChild(messageDiv);
      chatBox.scrollTop = chatBox.scrollHeight;
    }
    
    // Load chat history when page loads
    document.addEventListener('DOMContentLoaded', function() {
      loadChatHistory();
    });
    
    function loadChatHistory() {
      fetch('get_chat_history.php')
        .then(response => response.json())
        .then(data => {
          const chatBox = document.getElementById('chat-box');
          chatBox.innerHTML = '';
          
          if (data.messages && data.messages.length > 0) {
            data.messages.forEach(msg => {
              const messageDiv = document.createElement('div');
              messageDiv.classList.add('message', msg.is_bot ? 'bot' : 'user');
              
              const textDiv = document.createElement('div');
              textDiv.classList.add('text');
              textDiv.textContent = msg.content;
              
              messageDiv.appendChild(textDiv);
              chatBox.appendChild(messageDiv);
            });
          } else {
            // No history or new chat, show welcome message
            addBotMessage("Hello! How can I help you today?");
          }
          
          chatBox.scrollTop = chatBox.scrollHeight;
        })
        .catch(error => {
          console.error('Error loading chat history:', error);
          addBotMessage("Hello! How can I help you today?");
        });
    }
  </script>
  <script src="chat.js"></script>
</body>
</html>