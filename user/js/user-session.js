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
      // Not logged in - but DON'T redirect automatically
      console.log('User not logged in (session check)');
      return null;
    }
  } catch (error) {
    console.error('Error fetching user session:', error);
    // Try to use localStorage as fallback
    const fallbackUser = getUserFromLocalStorage();
    if (fallbackUser && fallbackUser.id) {
      return fallbackUser;
    }
    // If no valid user data, return null but don't redirect
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

function logout() {
  console.log('Custom logout function called - opening modal');
  openLogoutModal();
}


function openLogoutModal() {
  console.log('Opening logout modal');
  const modal = document.getElementById('logoutModal');
  if (modal) {
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
    console.log('Modal should be visible now');
  } else {
    console.error('Logout modal not found!');
  }
}
// Close logout confirmation modal
function closeLogoutModal() {
  const modal = document.getElementById('logoutModal');
  if (modal) {
    modal.classList.remove('active');
    document.body.style.overflow = ''; // Restore scrolling
    
    // Reset loading state if any
    const confirmBtn = document.getElementById('confirmLogoutBtn');
    if (confirmBtn) {
      confirmBtn.classList.remove('loading');
      confirmBtn.disabled = false;
      confirmBtn.innerHTML = '<i class="fas fa-sign-out-alt" style="margin-right: 5px;"></i> Yes, Sign Out';
    }
  }
}

// Confirm and execute logout
async function confirmLogout() {
  const confirmBtn = document.getElementById('confirmLogoutBtn');
  
  // Show loading state
  if (confirmBtn) {
    confirmBtn.classList.add('loading');
    confirmBtn.disabled = true;
    confirmBtn.innerHTML = 'Signing out...';
  }
  
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
    // Always clear client data and redirect to HOME
    localStorage.clear();
    sessionStorage.clear();
    
    // IMMEDIATE redirect - don't wait for anything
    window.location.replace('../../index.php?logout=true&t=' + Date.now());
  }
}

// Close modal when clicking outside
document.addEventListener('DOMContentLoaded', function() {
  const logoutModal = document.getElementById('logoutModal');
  if (logoutModal) {
    logoutModal.addEventListener('click', function(e) {
      if (e.target === logoutModal) {
        closeLogoutModal();
      }
    });
  }
  
  // Close modal with Escape key
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
      closeLogoutModal();
    }
  });
});
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

