document.addEventListener("DOMContentLoaded", async () => {
    // Load profile
    if (typeof loadUserProfile === "function") await loadUserProfile();

    // Setup notifications
    if (typeof setupNotifications === "function") setupNotifications();
    if (typeof fetchNotifications === "function") await fetchNotifications();

    // Optional: auto-refresh notifications every 30s
    setInterval(() => {
        if (typeof fetchNotifications === "function") fetchNotifications();
    }, 30000);
});
