<?php
/**
 * Notifications page template.
 *
 * @package Ryvr
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get notifications from the database.
if (class_exists('\\Ryvr\\Notifications\\Channels\\Platform_Channel')) {
    $platform_channel = new \Ryvr\Notifications\Channels\Platform_Channel();
    $notifications = $platform_channel->get_notifications(get_current_user_id(), false, 100);
    $unread_count = $platform_channel->get_unread_count(get_current_user_id());
} else {
    $notifications = [];
    $unread_count = 0;
}
?>

<div class="wrap ryvr-notifications-wrap">
    <h1><?php esc_html_e('Notifications', 'ryvr-ai'); ?></h1>
    
    <div class="ryvr-notifications-actions">
        <button type="button" class="button ryvr-mark-all-read">
            <?php esc_html_e('Mark All as Read', 'ryvr-ai'); ?>
        </button>
        
        <div class="ryvr-notifications-filters">
            <label>
                <input type="checkbox" id="ryvr-show-unread-only">
                <?php esc_html_e('Show Unread Only', 'ryvr-ai'); ?>
            </label>
        </div>
    </div>
    
    <div class="ryvr-notifications-list">
        <?php if (empty($notifications)) : ?>
            <div class="ryvr-no-notifications">
                <p><?php esc_html_e('No notifications found.', 'ryvr-ai'); ?></p>
            </div>
        <?php else : ?>
            <?php foreach ($notifications as $notification) : ?>
                <div class="ryvr-notification <?php echo $notification->read ? 'ryvr-notification-read' : 'ryvr-notification-unread'; ?>" data-id="<?php echo esc_attr($notification->id); ?>">
                    <div class="ryvr-notification-header">
                        <h3 class="ryvr-notification-title"><?php echo esc_html($notification->title); ?></h3>
                        <div class="ryvr-notification-meta">
                            <span class="ryvr-notification-date"><?php echo esc_html(human_time_diff(strtotime($notification->created_at), current_time('timestamp')) . ' ' . __('ago', 'ryvr-ai')); ?></span>
                            <span class="ryvr-notification-status"><?php echo $notification->read ? esc_html__('Read', 'ryvr-ai') : esc_html__('Unread', 'ryvr-ai'); ?></span>
                        </div>
                    </div>
                    
                    <div class="ryvr-notification-content">
                        <?php echo wp_kses_post(wpautop($notification->message)); ?>
                        
                        <?php if (!empty($notification->data) && isset($notification->data['task_url'])) : ?>
                            <p class="ryvr-notification-actions">
                                <a href="<?php echo esc_url($notification->data['task_url']); ?>" class="button button-primary">
                                    <?php esc_html_e('View Task', 'ryvr-ai'); ?>
                                </a>
                            </p>
                        <?php endif; ?>
                        
                        <?php if (!empty($notification->data) && isset($notification->data['approval_url'])) : ?>
                            <p class="ryvr-notification-actions">
                                <a href="<?php echo esc_url($notification->data['approval_url']); ?>" class="button button-primary">
                                    <?php esc_html_e('Approve Task', 'ryvr-ai'); ?>
                                </a>
                            </p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="ryvr-notification-actions">
                        <button type="button" class="button ryvr-toggle-read">
                            <?php echo $notification->read ? esc_html__('Mark as Unread', 'ryvr-ai') : esc_html__('Mark as Read', 'ryvr-ai'); ?>
                        </button>
                        <button type="button" class="button ryvr-delete-notification">
                            <?php esc_html_e('Delete', 'ryvr-ai'); ?>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <div class="ryvr-notifications-loading" style="display: none;">
        <span class="spinner is-active"></span>
        <p><?php esc_html_e('Loading...', 'ryvr-ai'); ?></p>
    </div>
</div>

<script type="text/javascript">
    jQuery(document).ready(function($) {
        // Mark notification as read/unread
        $('.ryvr-toggle-read').on('click', function() {
            var $button = $(this);
            var $notification = $button.closest('.ryvr-notification');
            var notificationId = $notification.data('id');
            var isRead = $notification.hasClass('ryvr-notification-read');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ryvr_mark_notification_read',
                    notification_id: notificationId,
                    mark_as: isRead ? 'unread' : 'read',
                    nonce: ryvrData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        if (isRead) {
                            $notification.removeClass('ryvr-notification-read').addClass('ryvr-notification-unread');
                            $notification.find('.ryvr-notification-status').text('Unread');
                            $button.text('Mark as Read');
                        } else {
                            $notification.removeClass('ryvr-notification-unread').addClass('ryvr-notification-read');
                            $notification.find('.ryvr-notification-status').text('Read');
                            $button.text('Mark as Unread');
                        }
                    }
                }
            });
        });
        
        // Mark all notifications as read
        $('.ryvr-mark-all-read').on('click', function() {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ryvr_mark_all_notifications_read',
                    nonce: ryvrData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('.ryvr-notification-unread').removeClass('ryvr-notification-unread').addClass('ryvr-notification-read');
                        $('.ryvr-notification-status').text('Read');
                        $('.ryvr-toggle-read').text('Mark as Unread');
                    }
                }
            });
        });
        
        // Delete notification
        $('.ryvr-delete-notification').on('click', function() {
            if (!confirm(ryvrData.strings.confirmDelete)) {
                return;
            }
            
            var $button = $(this);
            var $notification = $button.closest('.ryvr-notification');
            var notificationId = $notification.data('id');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ryvr_delete_notification',
                    notification_id: notificationId,
                    nonce: ryvrData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $notification.fadeOut(300, function() {
                            $(this).remove();
                            
                            if ($('.ryvr-notification').length === 0) {
                                $('.ryvr-notifications-list').html(
                                    '<div class="ryvr-no-notifications"><p>No notifications found.</p></div>'
                                );
                            }
                        });
                    }
                }
            });
        });
        
        // Filter notifications
        $('#ryvr-show-unread-only').on('change', function() {
            if ($(this).is(':checked')) {
                $('.ryvr-notification-read').hide();
            } else {
                $('.ryvr-notification-read').show();
            }
        });
    });
</script> 