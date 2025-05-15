<?php
/**
 * The Platform Channel class.
 *
 * Handles in-platform notifications.
 *
 * @package    Ryvr
 * @subpackage Ryvr/Notifications/Channels
 */

namespace Ryvr\Notifications\Channels;

/**
 * The Platform Channel class.
 *
 * This class handles in-platform notifications displayed in the WordPress admin.
 *
 * @package    Ryvr
 * @subpackage Ryvr/Notifications/Channels
 */
class Platform_Channel {

    /**
     * Send a notification via in-platform notification.
     *
     * @param int    $user_id User ID.
     * @param string $subject Notification subject.
     * @param string $body Notification body.
     * @param array  $data Additional data.
     * @return bool Whether the notification was stored.
     */
    public function send($user_id, $subject, $body, $data = []) {
        global $wpdb;
        
        // Get table name.
        $table_name = $wpdb->prefix . 'ryvr_platform_notifications';
        
        // Prepare data.
        $notification_data = [
            'user_id' => $user_id,
            'title' => $subject,
            'message' => $body,
            'data' => wp_json_encode($data),
            'read' => 0,
            'created_at' => current_time('mysql', true),
        ];
        
        // Insert notification.
        $result = $wpdb->insert(
            $table_name,
            $notification_data,
            [
                '%d',
                '%s',
                '%s',
                '%s',
                '%d',
                '%s',
            ]
        );
        
        if ($result) {
            // Trigger action for real-time notification.
            do_action('ryvr_platform_notification_created', $wpdb->insert_id, $user_id, $subject, $body, $data);
        }
        
        return $result !== false;
    }
    
    /**
     * Get unread notifications count for a user.
     *
     * @param int $user_id User ID.
     * @return int Unread count.
     */
    public function get_unread_count($user_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ryvr_platform_notifications';
        
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE user_id = %d AND read = 0",
                $user_id
            )
        );
        
        return (int) $count;
    }
    
    /**
     * Get notifications for a user.
     *
     * @param int  $user_id User ID.
     * @param bool $unread_only Only get unread notifications.
     * @param int  $limit Limit the number of notifications.
     * @return array Notifications.
     */
    public function get_notifications($user_id, $unread_only = false, $limit = 10) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ryvr_platform_notifications';
        
        $where = $wpdb->prepare('WHERE user_id = %d', $user_id);
        
        if ($unread_only) {
            $where .= ' AND read = 0';
        }
        
        $limit_sql = $limit > 0 ? $wpdb->prepare('LIMIT %d', $limit) : '';
        
        $notifications = $wpdb->get_results(
            "SELECT * FROM $table_name $where ORDER BY created_at DESC $limit_sql"
        );
        
        // Parse data JSON.
        foreach ($notifications as &$notification) {
            $notification->data = json_decode($notification->data, true);
        }
        
        return $notifications;
    }
    
    /**
     * Mark a notification as read.
     *
     * @param int $notification_id Notification ID.
     * @param int $user_id User ID for validation.
     * @return bool Whether the operation was successful.
     */
    public function mark_as_read($notification_id, $user_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ryvr_platform_notifications';
        
        $result = $wpdb->update(
            $table_name,
            ['read' => 1],
            [
                'id' => $notification_id,
                'user_id' => $user_id,
            ],
            ['%d'],
            ['%d', '%d']
        );
        
        return $result !== false;
    }
    
    /**
     * Mark all notifications as read for a user.
     *
     * @param int $user_id User ID.
     * @return bool Whether the operation was successful.
     */
    public function mark_all_as_read($user_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ryvr_platform_notifications';
        
        $result = $wpdb->update(
            $table_name,
            ['read' => 1],
            ['user_id' => $user_id, 'read' => 0],
            ['%d'],
            ['%d', '%d']
        );
        
        return $result !== false;
    }
    
    /**
     * Delete a notification.
     *
     * @param int $notification_id Notification ID.
     * @param int $user_id User ID for validation.
     * @return bool Whether the operation was successful.
     */
    public function delete_notification($notification_id, $user_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ryvr_platform_notifications';
        
        $result = $wpdb->delete(
            $table_name,
            [
                'id' => $notification_id,
                'user_id' => $user_id,
            ],
            ['%d', '%d']
        );
        
        return $result !== false;
    }
    
    /**
     * Delete all notifications for a user.
     *
     * @param int $user_id User ID.
     * @return bool Whether the operation was successful.
     */
    public function delete_all_notifications($user_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ryvr_platform_notifications';
        
        $result = $wpdb->delete(
            $table_name,
            ['user_id' => $user_id],
            ['%d']
        );
        
        return $result !== false;
    }
    
    /**
     * Clean up old notifications.
     *
     * @param int $days_to_keep Number of days to keep notifications.
     * @return int Number of notifications deleted.
     */
    public function cleanup_old_notifications($days_to_keep = 30) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ryvr_platform_notifications';
        
        $date = date('Y-m-d H:i:s', strtotime('-' . $days_to_keep . ' days'));
        
        $result = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $table_name WHERE created_at < %s",
                $date
            )
        );
        
        return $result;
    }
} 