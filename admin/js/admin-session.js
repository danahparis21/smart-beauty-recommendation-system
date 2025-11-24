// admin-session.js - Admin session management
let currentAdmin = null;

// Fetch current admin session data
async function fetchAdminSession() {
  try {
    const response = await fetch('../php/check-admin-session.php');
    const data = await response.json();
    
    if (data.success && data.isLoggedIn && data.isAdmin) {
      currentAdmin = data.user;
      saveAdminToLocalStorage(currentAdmin);
      return currentAdmin;
    } else {
      // Not logged in as admin - redirect to login
      console.log('Admin not logged in, redirecting...');
      window.location.href = '/user/html/login.html?redirect=' + encodeURIComponent(window.location.pathname);
      return null;
    }
  } catch (error) {
    console.error('Error fetching admin session:', error);
    // On error, redirect to login
    window.location.href = '/user/html/login.html?redirect=' + encodeURIComponent(window.location.pathname);
    return null;
  }
}

// Save admin data to localStorage
function saveAdminToLocalStorage(admin) {
  localStorage.setItem('adminId', admin.id);
  localStorage.setItem('adminUsername', admin.username);
  localStorage.setItem('adminFirstName', admin.firstName);
  localStorage.setItem('adminEmail', admin.email);
  localStorage.setItem('adminRole', admin.role);
}

// Get admin data from localStorage
function getAdminFromLocalStorage() {
  return {
    id: localStorage.getItem('adminId'),
    username: localStorage.getItem('adminUsername'),
    firstName: localStorage.getItem('adminFirstName'),
    email: localStorage.getItem('adminEmail'),
    role: localStorage.getItem('adminRole')
  };
}

// Update admin greeting in the UI
async function updateAdminGreeting() {
  if (!currentAdmin) {
    currentAdmin = await fetchAdminSession();
  }
  
  if (currentAdmin) {
    const greetingElement = document.getElementById('admin-greeting');
    if (greetingElement) {
      greetingElement.textContent = `Welcome, ${currentAdmin.firstName || currentAdmin.username}!`;
    }
    
    // Update admin name in sidebar or header if elements exist
    const adminNameElement = document.getElementById('admin-name');
    if (adminNameElement) {
      adminNameElement.textContent = currentAdmin.firstName || currentAdmin.username;
    }
    
    console.log('Admin logged in:', currentAdmin);
  }
}

// Verify admin session periodically
async function verifyAdminSession() {
  try {
    const response = await fetch('../php/check-admin-session.php?t=' + Date.now(), {
      cache: 'no-store',
      headers: {
        'Cache-Control': 'no-cache'
      }
    });
    const data = await response.json();
    
    if (!data.success || !data.isLoggedIn || !data.isAdmin) {
      console.log('Admin session expired, redirecting to login');
      localStorage.clear();
      sessionStorage.clear();
      window.location.href = '/user/html/login.html?session_expired=true';
    }
  } catch (error) {
    console.error('Admin session verification failed:', error);
    localStorage.clear();
    sessionStorage.clear();
    window.location.href = '/user/html/login.html?session_error=true';
  }
}

// Setup admin page protection
function setupAdminPageProtection() {
  // Check every 30 seconds
  setInterval(verifyAdminSession, 30000);
  
  // Check on visibility change
  document.addEventListener('visibilitychange', function() {
    if (!document.hidden) {
      verifyAdminSession();
    }
  });

  // Check on page navigation
  window.addEventListener('pageshow', function(event) {
    verifyAdminSession();
  });
}

// Admin logout function - USING SWEETALERT2
function adminLogout() {
  console.log('Admin logout called');
  
  Swal.fire({
    html: `
      <div style="display: flex; flex-direction: column; align-items: center; gap: 15px;">
        <img src="../images/mainlogo.png" alt="Blessence Logo" style="width: 80px; height: auto; border-radius: 12px;">
        <h2 style="color: #e497aa; margin: 0;">Logout Confirmation</h2>
        <p style="color: #6c757d; text-align: center; max-width: 250px;">
          Are you sure you want to logout from the admin dashboard?
        </p>
      </div>
    `,
    showCancelButton: true,
    confirmButtonText: 'Yes, Logout',
    cancelButtonText: 'Cancel',
    confirmButtonColor: '#e497aa',
    cancelButtonColor: '#6c757d',
    reverseButtons: true,
    background: '#ffffff',
    backdrop: true,
    customClass: {
      popup: 'soft-popup'
    }
  }).then((result) => {
    if (result.isConfirmed) {
      confirmAdminLogout();
    }
  });
}

// Actual logout execution
async function confirmAdminLogout() {
    try {
      // Clear server session (happens in background)
      await fetch('/user/php/logout.php', {
        method: 'POST',
        headers: { 'Cache-Control': 'no-cache' }
      });
    } catch (error) {
      console.error('Admin logout request failed:', error);
    } finally {
      // Clear client data and redirect immediately
      localStorage.clear();
      sessionStorage.clear();
      window.location.replace('../../index.php?logout=true&t=' + Date.now());
    }
  }
// Setup logout button event listener
function setupLogoutButton() {
  const logoutBtn = document.getElementById('logoutBtn');
  if (logoutBtn) {
    logoutBtn.addEventListener('click', function(e) {
      e.preventDefault();
      adminLogout();
    });
  }
}

// Initialize admin session management
document.addEventListener('DOMContentLoaded', function() {
  // Setup logout button
  setupLogoutButton();
});