// user-session.js - Shared user session management
let currentUser = null;

// Fetch current user session data
async function fetchUserSession() {
  try {
    const response = await fetch('../php/get-user-session.php');
    const data = await response.json();
    
    if (data.success && data.isLoggedIn) {
      currentUser = data.user;
      // Save to localStorage for quick access and offline fallback
      saveUserToLocalStorage(currentUser);
      return currentUser;
    } else {
      // Not logged in - redirect to login
      console.warn('User not logged in, redirecting...');
      window.location.href = '/user/html/login.html';
      return null;
    }
  } catch (error) {
    console.error('Error fetching user session:', error);
    // Try to use localStorage as fallback
    const fallbackUser = getUserFromLocalStorage();
    if (fallbackUser && fallbackUser.id) {
      return fallbackUser;
    }
    // If no valid user data, redirect to login
    window.location.href = '/user/html/login.html';
    return null;
  }
}

// Save user data to localStorage
function saveUserToLocalStorage(user) {
  localStorage.setItem('userId', user.id);
  localStorage.setItem('username', user.username);
  localStorage.setItem('firstName', user.firstName);
  localStorage.setItem('lastName', user.lastName || '');
  localStorage.setItem('email', user.email);
  localStorage.setItem('role', user.role);
}

// Get user data from localStorage
function getUserFromLocalStorage() {
  return {
    id: localStorage.getItem('userId'),
    username: localStorage.getItem('username'),
    firstName: localStorage.getItem('firstName'),
    lastName: localStorage.getItem('lastName'),
    email: localStorage.getItem('email'),
    role: localStorage.getItem('role')
  };
}

// Update user greeting with actual username from database
async function updateUserGreeting() {
  if (!currentUser) {
    currentUser = await fetchUserSession();
  }
  
  if (currentUser) {
    const greetingElement = document.getElementById('user-greeting');
    if (greetingElement) {
      greetingElement.textContent = `Hi, ${currentUser.firstName || currentUser.username}!`;
    }
    
    // Log user info for debugging
    const pageName = document.title.split('|')[0].trim();
    console.log(`${pageName} - Current User:`, {
      id: currentUser.id,
      username: currentUser.username,
      firstName: currentUser.firstName,
      email: currentUser.email
    });
  }
}

// Check if user is admin
function isAdmin() {
  return currentUser && currentUser.role === 'admin';
}

// Logout function
function logout() {
  // Clear session on server
  fetch('../php/logout.php')
    .then(() => {
      // Clear localStorage
      localStorage.clear();
      // Redirect to login
      window.location.href = '/user/html/login.html';
    })
    .catch(error => {
      console.error('Logout error:', error);
      // Still redirect even if request fails
      localStorage.clear();
      window.location.href = '/user/html/login.html';
    });
}

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
  module.exports = {
    fetchUserSession,
    updateUserGreeting,
    isAdmin,
    logout,
    getCurrentUser: () => currentUser
  };
}