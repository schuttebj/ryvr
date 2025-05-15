/**
 * Notification Indicator JavaScript
 *
 * Handles updating the notification indicator in the admin bar.
 */

(function($) {
    'use strict';
    
    // Initialize
    $(document).ready(function() {
        initNotificationIndicator();
    });
    
    /**
     * Initialize notification indicator.
     */
    function initNotificationIndicator() {
        // Set up periodic check for new notifications
        if (typeof rvyrNotifications !== 'undefined') {
            // Check for new notifications every 2 minutes
            setInterval(checkForNewNotifications, 120000);
            
            // Make the notification indicator function available globally
            window.updateNotificationMenuCount = updateNotificationMenuCount;
        }
    }
    
    /**
     * Check for new notifications.
     */
    function checkForNewNotifications() {
        $.ajax({
            url: rvyrNotifications.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ryvr_get_notifications',
                unread_only: true,
                limit: 0, // We only need the count, not the notifications themselves
                nonce: rvyrNotifications.nonce
            },
            success: function(response) {
                if (response.success) {
                    updateNotificationIndicator(response.data.unread_count);
                }
            }
        });
    }
    
    /**
     * Update the notification indicator in the admin bar.
     *
     * @param {number} count Unread count.
     */
    function updateNotificationIndicator(count) {
        var $indicator = $('#wp-admin-bar-ryvr-notifications');
        
        if ($indicator.length > 0) {
            if (count > 0) {
                $indicator.find('.ab-label').text(count);
                $indicator.find('.ab-item').attr('title', count + ' unread notification' + (count === 1 ? '' : 's'));
                $indicator.show();
            } else {
                $indicator.hide();
            }
        }
    }
    
    /**
     * Update notification count in the admin menu.
     *
     * @param {number} count Unread count.
     */
    function updateNotificationMenuCount(count) {
        var $menuItem = $('#toplevel_page_ryvr-dashboard .wp-submenu li a[href$="page=ryvr-notifications"]');
        
        if ($menuItem.length > 0) {
            // Remove existing count
            $menuItem.find('.update-plugins').remove();
            
            if (count > 0) {
                // Add count
                $menuItem.append(
                    '<span class="update-plugins count-' + count + '">' +
                    '<span class="plugin-count">' + count + '</span>' +
                    '</span>'
                );
            }
        }
    }
})(jQuery); 