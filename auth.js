document.addEventListener('DOMContentLoaded', function() {
  const auth = firebase.auth();
  const provider = new firebase.auth.GoogleAuthProvider();
  
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
  
// Login form submission - modified code
const loginForm = document.getElementById('login-form');
if (loginForm) {
  loginForm.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const email = document.getElementById('email').value;
    const password = document.getElementById('password').value;
    
    // Client-side validation
    if (!email || !password) {
      showAlert('Please fill in all fields', 'error');
      return;
    }
    
    // Show loading state
    showAlert('Logging in...', 'info');
    
    // Use fetch API instead of traditional form submission
    fetch(loginForm.action, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: new URLSearchParams({
        'email': email,
        'password': password
      })
    })
    .then(response => {
      // First check if redirect occurred
      if (response.redirected) {
        window.location.href = response.url;
        return;
      }
      
      // Otherwise try to parse JSON response
      return response.text().then(text => {
        try {
          return JSON.parse(text);
        } catch (e) {
          // If not valid JSON, check if it's HTML with redirect
          if (text.includes("<script>") && text.includes("window.location")) {
            // Extract redirect URL if possible
            const match = text.match(/window\.location\.href\s*=\s*['"]([^'"]+)['"]/);
            if (match) {
              window.location.href = match[1];
              return;
            }
          }
          console.error("Non-JSON response:", text);
          return { success: false, message: "Server error. Please try again." };
        }
      });
    })
    .then(data => {
      // Only process if we got JSON data (and not already redirected)
      if (data) {
        if (data.success) {
          showAlert(data.message || 'Login successful!', 'success');
          // Redirect after short delay
          setTimeout(() => {
            window.location.href = data.redirect || 'index.php';
          }, 1000);
        } else {
          showAlert(data.message || 'Login failed. Please check your credentials.', 'error');
        }
      }
    })
    .catch(error => {
      console.error('Error during login:', error);
      showAlert('Network error. Please check your connection and try again.', 'error');
    });
  });
}
  
// Signup form submission
const signupForm = document.getElementById('signup-form');
if (signupForm) {
  signupForm.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const name = document.getElementById('name').value;
    const email = document.getElementById('email').value;
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirm-password').value;
    
    // Client-side validation
    if (!name || !email || !password || !confirmPassword) {
      showAlert('Please fill in all fields', 'error');
      return;
    }
    
    // Password validation
    if (password.length < 8) {
      showAlert('Password must be at least 8 characters long', 'error');
      return;
    }
    
    // Password complexity check
    const passwordRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/;
    if (!passwordRegex.test(password)) {
      showAlert('Password must contain uppercase, lowercase, number, and special character', 'error');
      return;
    }
    
    // Check if passwords match
    if (password !== confirmPassword) {
      showAlert('Passwords do not match', 'error');
      return;
    }
    
    // Show loading state
    showAlert('Creating your account...', 'info');
    
    // Submit the form using fetch API with improved error handling
    fetch(signupForm.action, {
      method: 'POST',
      body: new FormData(signupForm)
    })
    .then(response => {
      // Get the response text first to inspect what's coming back
      return response.text().then(text => {
        try {
          // Try to parse as JSON
          const data = JSON.parse(text);
          return { ok: response.ok, data };
        } catch (e) {
          // If not valid JSON, return the text for debugging
          console.error("Server returned non-JSON response:", text);
          return { 
            ok: false, 
            data: { 
              success: false, 
              message: "Server error. Response was not valid JSON. Check with administrator." 
            } 
          };
        }
      });
    })
    .then(({ ok, data }) => {
      if (ok && data.success) {
        showAlert(data.message || 'Registration successful!', 'success');
        // Redirect to login page after successful registration
        setTimeout(() => {
          window.location.href = 'login.html';
        }, 2000);
      } else {
        showAlert(data.message || 'Registration failed. Please try again.', 'error');
      }
    })
    .catch(error => {
      console.error('Error submitting form:', error);
      showAlert('Network error. Please check your connection and try again.', 'error');
    });
  });
}
  
  // Google sign-in
  const googleLoginBtn = document.getElementById('google-login');
  if (googleLoginBtn) {
    googleLoginBtn.addEventListener('click', function() {
      // Set a flag to prevent redirect loops
      window.isProcessingLogin = true;
      
      auth.signInWithPopup(provider)
        .then((result) => {
          // Handle successful Google sign-in
          const user = result.user;
          
          // Show a loading indicator or message
          showAlert('Signing in with Google...', 'info');
          
          // Send user data to PHP backend
          return fetch('google_auth.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
            },
            body: JSON.stringify({
              uid: user.uid,
              email: user.email,
              name: user.displayName,
              photoURL: user.photoURL,
              action: 'login'
            }),
          });
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            // Clear the processing flag first
            window.isProcessingLogin = false;
            // Redirect to index page
            window.location.href = 'index.php';
          } else {
            window.isProcessingLogin = false;
            showAlert(data.message || 'Error signing in with Google', 'error');
          }
        })
        .catch((error) => {
          // Handle errors
          window.isProcessingLogin = false;
          showAlert(error.message, 'error');
          console.error(error);
        });
    });
  }

  // Similarly update the Google sign-up handler with the same pattern
  const googleSignupBtn = document.getElementById('google-signup');
  if (googleSignupBtn) {
    googleSignupBtn.addEventListener('click', function() {
      // Set a flag to prevent redirect loops
      window.isProcessingLogin = true;
      
      auth.signInWithPopup(provider)
        .then((result) => {
          // Handle successful Google sign-up
          const user = result.user;
          
          // Show a loading indicator or message
          showAlert('Creating account with Google...', 'info');
          
          // Send user data to PHP backend
          return fetch('google_auth.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
            },
            body: JSON.stringify({
              uid: user.uid,
              email: user.email,
              name: user.displayName,
              photoURL: user.photoURL,
              action: 'signup'
            }),
          });
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            // Clear the processing flag first
            window.isProcessingLogin = false;
            // Redirect to index page
            window.location.href = 'index.php';
          } else {
            window.isProcessingLogin = false;
            showAlert(data.message || 'Error signing up with Google', 'error');
          }
        })
        .catch((error) => {
          // Handle errors
          window.isProcessingLogin = false;
          showAlert(error.message, 'error');
          console.error(error);
        });
    });
  }
  
  // Check if user is already signed in
  auth.onAuthStateChanged(function(user) {
    // Only redirect if we're not already processing a login
    if (user && window.location.pathname.includes('login.html') && !window.isProcessingLogin) {
      // Set a flag to prevent redirect loops
      window.isProcessingLogin = true;
      
      // Verify with your backend first
      fetch('check_session.php')
      .then(response => response.json())
      .then(data => {
        if (data.logged_in) {
          window.location.href = 'index.php';
        } else {
          // User is authenticated in Firebase but not in your PHP session
          // Force a Google auth to sync them
          auth.signInWithPopup(provider)
          .then(result => {
              // Handle through your existing Google login process
          });
        }
      })
      .catch(error => {
        console.error('Session check failed:', error);
      });
    }
  });
  
});