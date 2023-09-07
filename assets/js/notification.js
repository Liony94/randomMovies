function updateNotificationIndicator(hasUnreadNotifications) {
    const indicator = document.querySelector('.notification-indicator');
    if (hasUnreadNotifications) {
        indicator.classList.remove('hidden');
    } else {
        indicator.classList.add('hidden');
    }
}