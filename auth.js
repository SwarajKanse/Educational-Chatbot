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
  
  // Login form submission
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
      
      // Submit the form
      loginForm.submit();
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
      
      // Submit the form
      signupForm.submit();
    });
  }
  
  // Google sign-in
  const googleLoginBtn = document.getElementById('google-login');
  if (googleLoginBtn) {
    googleLoginBtn.addEventListener('click', function() {
      auth.signInWithPopup(provider)
        .then((result) => {
          // Handle successful Google sign-in
          const user = result.user;
          
          // Send user data to PHP backend
          fetch('google_auth.php', {
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
          })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              window.location.href = 'index.php';
            } else {
              showAlert(data.message || 'Error signing in with Google', 'error');
            }
          })
          .catch(error => {
            showAlert('Error communicating with server', 'error');
            console.error(error);
          });
        })
        .catch((error) => {
          // Handle errors
          showAlert(error.message, 'error');
          console.error(error);
        });
    });
  }
  
  // Google sign-up
  const googleSignupBtn = document.getElementById('google-signup');
  if (googleSignupBtn) {
    googleSignupBtn.addEventListener('click', function() {
      auth.signInWithPopup(provider)
        .then((result) => {
          // Handle successful Google sign-up
          const user = result.user;
          
          // Send user data to PHP backend
          fetch('google_auth.php', {
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
          })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              window.location.href = 'index.php';
            } else {
              showAlert(data.message || 'Error signing up with Google', 'error');
            }
          })
          .catch(error => {
            showAlert('Error communicating with server', 'error');
            console.error(error);
          });
        })
        .catch((error) => {
          // Handle errors
          showAlert(error.message, 'error');
          console.error(error);
        });
    });
  }
  
  // Check if user is already signed in
  auth.onAuthStateChanged(function(user) {
    if (user && window.location.pathname.includes('login.html')) {
      // User is signed in and on login page, redirect to index
      window.location.href = 'index.php';
    }
  });
});