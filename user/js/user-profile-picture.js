async function loadUserProfile() {
    try {
        const res = await fetch('../php/get-user-profile.php');
        const data = await res.json();
        
        console.log('Profile API response:', data);
        
        if (data.success && data.user) {
            const user = data.user;

            // Update greeting
            const greeting = document.getElementById('user-greeting');
            if (greeting) {
                const displayName = user.first_name || user.username || 'User';
                greeting.textContent = `Hi, ${displayName}!`;
            }

            // Function to update profile buttons
            const updateProfileButton = (buttonElement) => {
                if (user.profile_photo && user.profile_photo.trim() !== '' && user.profile_photo !== 'default.png') {
                    // ✅ CORRECTED PATH: uploads/profiles/
                    const profileImagePath = `../../uploads/profiles/${user.profile_photo}`;
                    
                    console.log('Loading profile image from:', profileImagePath); // Debug log
                    
                    buttonElement.innerHTML = `<img src="${profileImagePath}" alt="Profile" class="profile-icon" style="width: 30px; height: 30px; border-radius: 50%; object-fit: cover; border: 2px solid #ff69b4;">`;
                    // Add error handling for the image
                    const img = buttonElement.querySelector('img');
                    if (img) {
                        img.onerror = function() {
                            console.warn('Profile image failed to load:', profileImagePath);
                            // Fallback to icon
                            buttonElement.innerHTML = '<i class="fas fa-user"></i>';
                        };
                    }
                } else {
                    // No profile photo, use default icon
                    buttonElement.innerHTML = '<i class="fas fa-user"></i>';
                }
            };

            // Update both desktop and mobile buttons
            const desktopProfileBtn = document.querySelector('.profile-btn');
            const mobileProfileBtn = document.querySelector('.mobile-profile-btn');
            
            if (desktopProfileBtn) updateProfileButton(desktopProfileBtn);
            if (mobileProfileBtn) updateProfileButton(mobileProfileBtn);
            
        } else {
            console.warn('Failed to load user profile:', data.message);
        }
    } catch (err) {
        console.error('Failed to load user profile:', err);
    }
}