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

// Enhanced logout function
async function logout() {
  if (confirm("Are you sure you want to sign out?")) {
      try {
          // Clear server session
          await fetch('../php/logout.php', {
              method: 'POST',
              headers: {
                  'Cache-Control': 'no-cache'
              }
          });
      } catch (error) {
          console.error('Logout request failed:', error);
      } finally {
          // Always clear client data and redirect
          localStorage.clear();
          sessionStorage.clear();
          
          // Force reload to clear any cached state
          window.location.href = '/user/html/login.html?logout=true&t=' + Date.now();
      }
  }
}
function setupPageProtection() {
  // Check auth when page becomes visible (including back/forward navigation)
  document.addEventListener('visibilitychange', function() {
      if (!document.hidden) {
          // Page became visible - verify user is still logged in
          verifySession();
      }
  });

  // Also check on pageshow event (for back/forward cache)
  window.addEventListener('pageshow', function(event) {
      if (event.persisted) {
          // Page loaded from bfcache
          verifySession();
      }
  });
}

// Number formatting function - ADD THIS
function formatNumberWithCommas(number) {
  // Handle both numbers and strings
  const num = typeof number === 'string' ? parseFloat(number) : number;
  
  // Split into whole and decimal parts
  const parts = num.toFixed(2).split('.');
  const wholePart = parts[0];
  const decimalPart = parts[1] || '00';
  
  // Add commas to whole number part
  const formattedWhole = wholePart.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  
  return `${formattedWhole}.${decimalPart}`;
}


// Add this function to verify session
async function verifySession() {
  try {
      const response = await fetch('../php/get-user-session.php?t=' + Date.now());
      const data = await response.json();
      
      if (!data.success || !data.isLoggedIn) {
          // Session is no longer valid
          console.log('Session expired, redirecting to login');
          window.location.href = '/user/html/login.html';
      }
  } catch (error) {
      console.error('Session verification failed:', error);
      // On error, redirect to login to be safe
      window.location.href = '/user/html/login.html';
  }
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

