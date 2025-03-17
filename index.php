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
    .header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 10px 20px;
      background-color: #fff;
      box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    }
    
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
    
    .action-buttons {
      display: flex;
      gap: 10px;
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
  </style>
</head>
<body>
  <div class="header">
    <div class="action-buttons">
      <button id="new-chat-btn" class="btn-new-chat">Start New Chat</button>
    </div>
    <div class="user-menu">
      <div class="user-info" id="user-dropdown-toggle">
        <div class="user-avatar">
          <?php echo substr($user_name, 0, 1); ?>
        </div>
        <span><?php echo htmlspecialchars($user_name); ?></span>
      </div>
      <div class="dropdown-menu" id="user-dropdown">
        <div class="dropdown-item"><?php echo htmlspecialchars($user_email); ?></div>
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
            // Optionally display welcome message
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
      messageDiv.className = 'message bot-message';
      messageDiv.textContent = message;
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
              messageDiv.className = `message ${msg.is_bot ? 'bot-message' : 'user-message'}`;
              messageDiv.textContent = msg.content;
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