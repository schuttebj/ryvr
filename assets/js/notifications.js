/**
 * Notifications JavaScript
 *
 * Handles the functionality for the notifications admin page.
 */

(function($) {
    'use strict';
    
    // Initialize
    $(document).ready(function() {
        initNotifications();
    });
    
    /**
     * Initialize notifications functionality.
     */
    function initNotifications() {
        // Set up event handlers
        setupEventHandlers();
        
        // Check if we should only show unread
        var showOnlyUnread = $('#ryvr-show-unread-only').is(':checked');
        if (showOnlyUnread) {
            refreshNotifications(true);
        }
    }
    
    /**
     * Set up event handlers.
     */
    function setupEventHandlers() {
        // Toggle read status
        $('.ryvr-notifications-list').on('click', '.ryvr-toggle-read', function() {
            var $notification = $(this).closest('.ryvr-notification');
            var notificationId = $notification.data('id');
            var isRead = $notification.hasClass('ryvr-notification-read');
            
            toggleReadStatus(notificationId, isRead, $notification);
        });
        
        // Delete notification
        $('.ryvr-notifications-list').on('click', '.ryvr-delete-notification', function() {
            var $notification = $(this).closest('.ryvr-notification');
            var notificationId = $notification.data('id');
            
            if (confirm(rvyrNotifications.strings.confirmDelete)) {
                deleteNotification(notificationId, $notification);
            }
        });
        
        // Mark all as read
        $('.ryvr-mark-all-read').on('click', function() {
            markAllAsRead();
        });
        
        // Toggle show unread only
        $('#ryvr-show-unread-only').on('change', function() {
            var showOnlyUnread = $(this).is(':checked');
            refreshNotifications(showOnlyUnread);
        });
    }
    
    /**
     * Toggle read status of a notification.
     *
     * @param {number} notificationId Notification ID.
     * @param {boolean} isRead Whether notification is currently read.
     * @param {jQuery} $notification Notification element.
     */
    function toggleReadStatus(notificationId, isRead, $notification) {
        showLoading();
        
        $.ajax({
            url: rvyrNotifications.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ryvr_mark_notification_read',
                notification_id: notificationId,
                mark_as: isRead ? 'unread' : 'read',
                nonce: rvyrNotifications.nonce
            },
            success: function(response) {
                hideLoading();
                
                if (response.success) {
                    // Update notification appearance
                    if (isRead) {
                        $notification.removeClass('ryvr-notification-read').addClass('ryvr-notification-unread');
                        $notification.find('.ryvr-notification-status').text(rvyrNotifications.strings.unread);
                        $notification.find('.ryvr-toggle-read').text(rvyrNotifications.strings.markRead);
                    } else {
                        $notification.removeClass('ryvr-notification-unread').addClass('ryvr-notification-read');
                        $notification.find('.ryvr-notification-status').text(rvyrNotifications.strings.read);
                        $notification.find('.ryvr-toggle-read').text(rvyrNotifications.strings.markUnread);
                    }
                    
                    // Update counts
                    updateCounts(response.data.unread_count);
                    
                    // If showing only unread and marking as read, hide the notification
                    if (!isRead && $('#ryvr-show-unread-only').is(':checked')) {
                        $notification.fadeOut(300, function() {
                            $(this).remove();
                            checkNoNotifications();
                        });
                    }
                } else {
                    alert(response.data.message || rvyrNotifications.strings.error);
                }
            },
            error: function() {
                hideLoading();
                alert(rvyrNotifications.strings.error);
            }
        });
    }
    
    /**
     * Delete a notification.
     *
     * @param {number} notificationId Notification ID.
     * @param {jQuery} $notification Notification element.
     */
    function deleteNotification(notificationId, $notification) {
        showLoading();
        
        $.ajax({
            url: rvyrNotifications.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ryvr_delete_notification',
                notification_id: notificationId,
                nonce: rvyrNotifications.nonce
            },
            success: function(response) {
                hideLoading();
                
                if (response.success) {
                    // Remove notification from DOM
                    $notification.fadeOut(300, function() {
                        $(this).remove();
                        checkNoNotifications();
                    });
                    
                    // Update counts
                    updateCounts(response.data.unread_count);
                } else {
                    alert(response.data.message || rvyrNotifications.strings.error);
                }
            },
            error: function() {
                hideLoading();
                alert(rvyrNotifications.strings.error);
            }
        });
    }
    
    /**
     * Mark all notifications as read.
     */
    function markAllAsRead() {
        showLoading();
        
        $.ajax({
            url: rvyrNotifications.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ryvr_mark_all_notifications_read',
                nonce: rvyrNotifications.nonce
            },
            success: function(response) {
                hideLoading();
                
                if (response.success) {
                    // Update all notifications in the list
                    $('.ryvr-notification-unread').each(function() {
                        $(this).removeClass('ryvr-notification-unread').addClass('ryvr-notification-read');
                        $(this).find('.ryvr-notification-status').text(rvyrNotifications.strings.read);
                        $(this).find('.ryvr-toggle-read').text(rvyrNotifications.strings.markUnread);
                    });
                    
                    // Update counts
                    updateCounts(0);
                    
                    // If showing only unread, refresh to show no results
                    if ($('#ryvr-show-unread-only').is(':checked')) {
                        refreshNotifications(true);
                    }
                } else {
                    alert(response.data.message || rvyrNotifications.strings.error);
                }
            },
            error: function() {
                hideLoading();
                alert(rvyrNotifications.strings.error);
            }
        });
    }
    
    /**
     * Refresh notifications list.
     *
     * @param {boolean} unreadOnly Whether to show only unread notifications.
     */
    function refreshNotifications(unreadOnly) {
        showLoading();
        
        $.ajax({
            url: rvyrNotifications.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ryvr_get_notifications',
                unread_only: unreadOnly,
                limit: 100,
                nonce: rvyrNotifications.nonce
            },
            success: function(response) {
                hideLoading();
                
                if (response.success) {
                    renderNotifications(response.data.notifications);
                    updateCounts(response.data.unread_count);
                } else {
                    alert(response.data.message || rvyrNotifications.strings.error);
                }
            },
            error: function() {
                hideLoading();
                alert(rvyrNotifications.strings.error);
            }
        });
    }
    
    /**
     * Render notifications in the list.
     *
     * @param {Array} notifications Notifications to render.
     */
    function renderNotifications(notifications) {
        var $list = $('.ryvr-notifications-list');
        $list.empty();
        
        if (notifications.length === 0) {
            // Show no notifications message
            $list.html('<div class="ryvr-no-notifications"><p>' + rvyrNotifications.strings.noNotifications + '</p></div>');
            return;
        }
        
        // Render each notification
        $.each(notifications, function(index, notification) {
            var isRead = notification.read;
            var readClass = isRead ? 'ryvr-notification-read' : 'ryvr-notification-unread';
            var readStatusText = isRead ? rvyrNotifications.strings.read : rvyrNotifications.strings.unread;
            var toggleReadText = isRead ? rvyrNotifications.strings.markUnread : rvyrNotifications.strings.markRead;
            
            var $notification = $('<div class="ryvr-notification ' + readClass + '" data-id="' + notification.id + '">' +
                '<div class="ryvr-notification-header">' +
                    '<h3 class="ryvr-notification-title">' + notification.title + '</h3>' +
                    '<div class="ryvr-notification-meta">' +
                        '<span class="ryvr-notification-date">' + notification.created_at_human + '</span>' +
                        '<span class="ryvr-notification-status">' + readStatusText + '</span>' +
                    '</div>' +
                '</div>' +
                '<div class="ryvr-notification-content">' +
                    '<p>' + notification.message + '</p>' +
                '</div>' +
                '<div class="ryvr-notification-actions">' +
                    '<button type="button" class="button ryvr-toggle-read">' + toggleReadText + '</button>' +
                    '<button type="button" class="button ryvr-delete-notification">' + rvyrNotifications.strings.delete + '</button>' +
                '</div>' +
            '</div>');
            
            // Add task-specific action buttons if available
            if (notification.data && notification.data.task_url) {
                $notification.find('.ryvr-notification-content').append(
                    '<p class="ryvr-notification-actions">' +
                    '<a href="' + notification.data.task_url + '" class="button button-primary">View Task</a>' +
                    '</p>'
                );
            }
            
            if (notification.data && notification.data.approval_url) {
                $notification.find('.ryvr-notification-content').append(
                    '<p class="ryvr-notification-actions">' +
                    '<a href="' + notification.data.approval_url + '" class="button button-primary">Approve Task</a>' +
                    '</p>'
                );
            }
            
            $list.append($notification);
        });
    }
    
    /**
     * Check if there are no notifications and show message if needed.
     */
    function checkNoNotifications() {
        var $list = $('.ryvr-notifications-list');
        if ($list.children('.ryvr-notification').length === 0) {
            $list.html('<div class="ryvr-no-notifications"><p>' + rvyrNotifications.strings.noNotifications + '</p></div>');
        }
    }
    
    /**
     * Update notification counts in the UI.
     *
     * @param {number} unreadCount Unread notification count.
     */
    function updateCounts(unreadCount) {
        // Update admin menu count
        if (typeof window.updateNotificationMenuCount === 'function') {
            window.updateNotificationMenuCount(unreadCount);
        }
        
        // Update admin bar if it exists
        var $adminBarCount = $('#wp-admin-bar-ryvr-notifications .ab-label');
        if ($adminBarCount.length > 0) {
            if (unreadCount > 0) {
                $adminBarCount.text(unreadCount);
                $('#wp-admin-bar-ryvr-notifications').show();
            } else {
                $('#wp-admin-bar-ryvr-notifications').hide();
            }
        }
    }
    
    /**
     * Show loading overlay.
     */
    function showLoading() {
        $('.ryvr-notifications-wrap').addClass('ryvr-loading');
        $('.ryvr-notifications-loading').show();
    }
    
    /**
     * Hide loading overlay.
     */
    function hideLoading() {
        $('.ryvr-notifications-wrap').removeClass('ryvr-loading');
        $('.ryvr-notifications-loading').hide();
    }
    
})(jQuery); 