function showNotification(message) {
    const existingNotification = document.querySelector(".cart-notification");
    if (existingNotification) existingNotification.remove();
    
    const notification = document.createElement("div");
    notification.className = `cart-notification success`;
    notification.innerHTML = `<div class="notification-content"><i class="fas fa-check-circle"></i><span>${message}</span></div>`;
    document.body.appendChild(notification);
    setTimeout(() => notification.classList.add("show"), 10);
    setTimeout(() => {
      notification.classList.remove("show");
      setTimeout(() => notification.remove(), 300);
    }, 3000);
}

  // --- NOTIFICATION LOGIC START ---
  async function fetchNotifications() {
    try {
        const response = await fetch('../php/get-notifications.php');
        const data = await response.json();
        
        const notifList = document.getElementById('notif-list');
        const desktopBadge = document.getElementById('notif-badge');
        const mobileBadge = document.getElementById('mobile-notif-badge'); // Add this
        
        if (data.success) {
            // Update Desktop Badge
            if (data.unreadCount > 0) {
                desktopBadge.style.display = 'flex';
                desktopBadge.textContent = data.unreadCount > 9 ? '9+' : data.unreadCount;
            } else {
                desktopBadge.style.display = 'none';
            }

            // Update Mobile Badge (add this section)
            if (mobileBadge) {
                if (data.unreadCount > 0) {
                    mobileBadge.style.display = 'flex';
                    mobileBadge.textContent = data.unreadCount > 9 ? '9+' : data.unreadCount;
                } else {
                    mobileBadge.style.display = 'none';
                }
            }

            // Render List
            notifList.innerHTML = '';
            if (data.notifications.length === 0) {
                notifList.innerHTML = '<div class="notif-empty">No notifications</div>';
            } else {
                data.notifications.forEach(notif => {
                    const item = document.createElement('div');
                    item.className = `notif-item ${notif.IsRead == 0 ? 'unread' : ''}`;
                    item.setAttribute('data-id', notif.NotificationID);
                    item.setAttribute('data-title', notif.Title);
                    item.setAttribute('data-message', notif.Message);
                    item.setAttribute('data-date', notif.FormattedDate);
                    
                    item.innerHTML = `
                        <div class="notif-title">${notif.Title}</div>
                        <div class="notif-date">${notif.FormattedDate}</div>
                    `;
                    
                    // Click event
                    item.onclick = function() {
                        markAsReadAndShow(this);
                    };
                    
                    notifList.appendChild(item);
                });
            }
        }
    } catch (error) {
        console.error('Error fetching notifications:', error);
    }
}

  // Function: Open modal + mark as read
  function markAsReadAndShow(element) {
    const id = element.getAttribute('data-id');
    const title = element.getAttribute('data-title');
    const message = element.getAttribute('data-message');
    const date = element.getAttribute('data-date');

    // Open modal
    document.getElementById('modal-title').textContent = title;
    document.getElementById('modal-date').textContent = date;
    document.getElementById('modal-message').textContent = message;
    document.getElementById('notif-detail-modal').style.display = 'flex';
    
    // Close dropdown
    document.getElementById('notif-dropdown').classList.remove('active');

    // Mark as read in UI immediately
    if (element.classList.contains('unread')) {
        element.classList.remove('unread');
        
        // Update both badges
        const desktopBadge = document.getElementById('notif-badge');
        const mobileBadge = document.getElementById('mobile-notif-badge');
        
        let count = parseInt(desktopBadge.textContent) || 0;
        if (count > 0) {
            count--;
            const badgeText = count > 9 ? '9+' : count;
            
            // Update desktop badge
            desktopBadge.textContent = badgeText;
            if (count === 0) desktopBadge.style.display = 'none';
            
            // Update mobile badge
            if (mobileBadge) {
                mobileBadge.textContent = badgeText;
                if (count === 0) mobileBadge.style.display = 'none';
            }
        }

        // Update database
        const formData = new FormData();
        formData.append('notification_id', id);

        fetch('../php/mark-notification-read.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => console.log("Marked read:", data))
        .catch(err => console.error("Error marking read:", err));
    }
}
  function closeNotifModal() {
      document.getElementById('notif-detail-modal').style.display = 'none';
  }
  function setupNotifications() {
    const desktopBtn = document.getElementById('notif-btn');
    const mobileBtn = document.getElementById('mobile-notif-btn');
    const dropdown = document.getElementById('notif-dropdown');

    // Desktop notification button
    if(desktopBtn && dropdown) {
        desktopBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            const isActive = dropdown.classList.contains('active');
            document.querySelectorAll('.notif-dropdown').forEach(d => d.classList.remove('active'));
            if(!isActive) {
                dropdown.classList.add('active');
                fetchNotifications();
            }
        });
    }

    // Mobile notification button
    if(mobileBtn && dropdown) {
        mobileBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            const isActive = dropdown.classList.contains('active');
            document.querySelectorAll('.notif-dropdown').forEach(d => d.classList.remove('active'));
            if(!isActive) {
                dropdown.classList.add('active');
                fetchNotifications();
            }
        });
    }

    // Close dropdown when clicking outside
    document.addEventListener('click', (e) => {
        if (dropdown && !dropdown.contains(e.target) && 
            !desktopBtn?.contains(e.target) && 
            !mobileBtn?.contains(e.target)) {
            dropdown.classList.remove('active');
        }
    });
}
  // --- NOTIFICATION LOGIC END ---
