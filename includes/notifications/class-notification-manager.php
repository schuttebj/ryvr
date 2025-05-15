<?php
/**
 * The Notification Manager class.
 *
 * Manages notifications in the Ryvr platform.
 *
 * @package    Ryvr
 * @subpackage Ryvr/Notifications
 */

namespace Ryvr\Notifications;

/**
 * The Notification Manager class.
 *
 * This class manages the creation, sending, and tracking of notifications.
 * Supports email, webhook, and in-platform notifications.
 *
 * @package    Ryvr
 * @subpackage Ryvr/Notifications
 */
class Notification_Manager {

    /**
     * Notification channels.
     *
     * @var array
     */
    private $channels = [];

    /**
     * Notification templates.
     *
     * @var array
     */
    private $templates = [];

    /**
     * Initialize the notification manager.
     *
     * @return void
     */
    public function init() {
        // Register default notification channels.
        $this->register_channels();
        
        // Register notification templates.
        $this->register_templates();
        
        // Register hooks.
        $this->register_hooks();
    }

    /**
     * Register notification channels.
     *
     * @return void
     */
    private function register_channels() {
        // Include channel classes.
        require_once RYVR_INCLUDES_DIR . 'notifications/channels/class-email-channel.php';
        require_once RYVR_INCLUDES_DIR . 'notifications/channels/class-webhook-channel.php';
        require_once RYVR_INCLUDES_DIR . 'notifications/channels/class-platform-channel.php';
        
        // Register built-in channels.
        $this->register_channel('email', new Channels\Email_Channel());
        $this->register_channel('webhook', new Channels\Webhook_Channel());
        $this->register_channel('platform', new Channels\Platform_Channel());
        
        // Allow other plugins to register notification channels.
        do_action('ryvr_register_notification_channels', $this);
    }

    /**
     * Register notification templates.
     *
     * @return void
     */
    private function register_templates() {
        // Task-related templates
        $this->register_template('task_created', [
            'name' => __('Task Created', 'ryvr-ai'),
            'description' => __('Sent when a new task is created.', 'ryvr-ai'),
            'subject' => __('New Task Created: {{task_title}}', 'ryvr-ai'),
            'body' => __('A new task "{{task_title}}" has been created. Task ID: {{task_id}}', 'ryvr-ai'),
            'channels' => ['email', 'platform'],
        ]);
        
        $this->register_template('task_approved', [
            'name' => __('Task Approved', 'ryvr-ai'),
            'description' => __('Sent when a task is approved.', 'ryvr-ai'),
            'subject' => __('Task Approved: {{task_title}}', 'ryvr-ai'),
            'body' => __('Your task "{{task_title}}" has been approved and is now being processed.', 'ryvr-ai'),
            'channels' => ['email', 'platform'],
        ]);
        
        $this->register_template('task_completed', [
            'name' => __('Task Completed', 'ryvr-ai'),
            'description' => __('Sent when a task is completed.', 'ryvr-ai'),
            'subject' => __('Task Completed: {{task_title}}', 'ryvr-ai'),
            'body' => __('Your task "{{task_title}}" has been completed successfully. View the results: {{task_url}}', 'ryvr-ai'),
            'channels' => ['email', 'platform', 'webhook'],
        ]);
        
        $this->register_template('task_failed', [
            'name' => __('Task Failed', 'ryvr-ai'),
            'description' => __('Sent when a task fails.', 'ryvr-ai'),
            'subject' => __('Task Failed: {{task_title}}', 'ryvr-ai'),
            'body' => __('Your task "{{task_title}}" has failed. Error: {{error_message}}', 'ryvr-ai'),
            'channels' => ['email', 'platform'],
        ]);
        
        $this->register_template('task_waiting_approval', [
            'name' => __('Task Waiting for Approval', 'ryvr-ai'),
            'description' => __('Sent when a task requires approval.', 'ryvr-ai'),
            'subject' => __('Task Waiting for Approval: {{task_title}}', 'ryvr-ai'),
            'body' => __('Task "{{task_title}}" is waiting for your approval. You can approve it here: {{approval_url}}', 'ryvr-ai'),
            'channels' => ['email', 'platform'],
        ]);
        
        // System and account notifications
        $this->register_template('credits_low', [
            'name' => __('Credits Low', 'ryvr-ai'),
            'description' => __('Sent when user credits are low.', 'ryvr-ai'),
            'subject' => __('Low Credits Alert', 'ryvr-ai'),
            'body' => __('Your account is running low on credits. Current balance: {{credits_balance}}. Purchase more credits to continue using the platform.', 'ryvr-ai'),
            'channels' => ['email', 'platform'],
        ]);
        
        $this->register_template('api_error', [
            'name' => __('API Error', 'ryvr-ai'),
            'description' => __('Sent when an API error occurs.', 'ryvr-ai'),
            'subject' => __('API Error Detected', 'ryvr-ai'),
            'body' => __('An error occurred with the {{api_name}} API. Error: {{error_message}}', 'ryvr-ai'),
            'channels' => ['email', 'platform'],
        ]);
        
        // Allow other plugins to register notification templates.
        do_action('ryvr_register_notification_templates', $this);
    }

    /**
     * Register WordPress hooks.
     *
     * @return void
     */
    private function register_hooks() {
        // Register AJAX actions.
        add_action('wp_ajax_ryvr_get_notifications', [$this, 'ajax_get_notifications']);
        add_action('wp_ajax_ryvr_mark_notification_read', [$this, 'ajax_mark_notification_read']);
        add_action('wp_ajax_ryvr_update_notification_preferences', [$this, 'ajax_update_notification_preferences']);
        
        // Register task hooks.
        add_action('ryvr_task_created', [$this, 'on_task_created'], 10, 3);
        add_action('ryvr_task_status_changed', [$this, 'on_task_status_changed'], 10, 3);
        
        // Register system hooks.
        add_action('ryvr_credits_low', [$this, 'on_credits_low'], 10, 2);
        add_action('ryvr_api_error', [$this, 'on_api_error'], 10, 3);
    }

    /**
     * Register a notification channel.
     *
     * @param string $channel_id Unique channel identifier.
     * @param object $channel Channel instance.
     * @return bool Whether the registration was successful.
     */
    public function register_channel($channel_id, $channel) {
        if (isset($this->channels[$channel_id])) {
            return false;
        }
        
        if (!method_exists($channel, 'send')) {
            return false;
        }
        
        $this->channels[$channel_id] = $channel;
        
        return true;
    }

    /**
     * Register a notification template.
     *
     * @param string $template_id Template identifier.
     * @param array  $args Template arguments.
     * @return bool Whether the registration was successful.
     */
    public function register_template($template_id, $args) {
        if (isset($this->templates[$template_id])) {
            return false;
        }
        
        $defaults = [
            'name' => '',
            'description' => '',
            'subject' => '',
            'body' => '',
            'channels' => ['email'],
        ];
        
        $this->templates[$template_id] = wp_parse_args($args, $defaults);
        
        return true;
    }

    /**
     * Send a notification.
     *
     * @param int    $user_id User ID.
     * @param string $template_id Template ID.
     * @param array  $data Data for template variables.
     * @param array  $channels Override default channels for this notification.
     * @return bool Whether the notification was sent.
     */
    public function send_notification($user_id, $template_id, $data = [], $channels = []) {
        // Check if template exists.
        if (!isset($this->templates[$template_id])) {
            return false;
        }
        
        // Get template.
        $template = $this->templates[$template_id];
        
        // Check user notification preferences if they've opted out of this type.
        if (!$this->user_wants_notification($user_id, $template_id)) {
            return false;
        }
        
        // Use specified channels or template default.
        $use_channels = !empty($channels) ? $channels : $template['channels'];
        
        // Replace variables in subject and body.
        $subject = $this->replace_variables($template['subject'], $data);
        $body = $this->replace_variables($template['body'], $data);
        
        $success = true;
        
        // Send through each channel.
        foreach ($use_channels as $channel_id) {
            if (!isset($this->channels[$channel_id])) {
                continue;
            }
            
            $channel = $this->channels[$channel_id];
            $result = $channel->send($user_id, $subject, $body, $data);
            
            if (!$result) {
                $success = false;
            }
        }
        
        // Log the notification.
        $this->log_notification($user_id, $template_id, $data, $use_channels);
        
        return $success;
    }

    /**
     * Check if a user wants to receive a specific notification.
     *
     * @param int    $user_id User ID.
     * @param string $template_id Template ID.
     * @return bool Whether the user wants to receive the notification.
     */
    private function user_wants_notification($user_id, $template_id) {
        // Get user preferences.
        $preferences = get_user_meta($user_id, 'ryvr_notification_preferences', true);
        
        // If no preferences are set, default to receiving notifications.
        if (empty($preferences)) {
            return true;
        }
        
        // Check if the user has explicitly disabled this notification.
        if (isset($preferences[$template_id]) && $preferences[$template_id] === false) {
            return false;
        }
        
        return true;
    }

    /**
     * Replace variables in a template string.
     *
     * @param string $template Template string.
     * @param array  $data Variable data.
     * @return string Processed string.
     */
    private function replace_variables($template, $data) {
        // Replace {{variable}} with data.
        return preg_replace_callback(
            '/\{\{([a-z0-9_]+)\}\}/i',
            function($matches) use ($data) {
                $var = $matches[1];
                return isset($data[$var]) ? $data[$var] : '';
            },
            $template
        );
    }

    /**
     * Log a notification.
     *
     * @param int    $user_id User ID.
     * @param string $template_id Template ID.
     * @param array  $data Data used in the notification.
     * @param array  $channels Channels used.
     * @return int|false The notification ID or false on failure.
     */
    private function log_notification($user_id, $template_id, $data, $channels) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ryvr_notifications';
        
        $result = $wpdb->insert(
            $table_name,
            [
                'user_id' => $user_id,
                'template_id' => $template_id,
                'data' => wp_json_encode($data),
                'channels' => wp_json_encode($channels),
                'read' => 0,
                'created_at' => current_time('mysql', true),
            ],
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
            return $wpdb->insert_id;
        }
        
        return false;
    }

    /**
     * Get user notifications.
     *
     * @param int  $user_id User ID.
     * @param bool $unread_only Only get unread notifications.
     * @param int  $limit Limit the number of notifications.
     * @return array Notifications.
     */
    public function get_user_notifications($user_id, $unread_only = false, $limit = 10) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ryvr_notifications';
        
        $where = $wpdb->prepare('WHERE user_id = %d', $user_id);
        
        if ($unread_only) {
            $where .= ' AND read = 0';
        }
        
        $limit_sql = $limit > 0 ? $wpdb->prepare('LIMIT %d', $limit) : '';
        
        $notifications = $wpdb->get_results(
            "SELECT * FROM {$table_name} {$where} ORDER BY created_at DESC {$limit_sql}"
        );
        
        return $notifications ?: [];
    }

    /**
     * Mark a notification as read.
     *
     * @param int $notification_id Notification ID.
     * @param int $user_id User ID for validation.
     * @return bool Whether the operation was successful.
     */
    public function mark_notification_read($notification_id, $user_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ryvr_notifications';
        
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
     * Get notification preferences for a user.
     *
     * @param int $user_id User ID.
     * @return array Preferences.
     */
    public function get_notification_preferences($user_id) {
        $preferences = get_user_meta($user_id, 'ryvr_notification_preferences', true);
        
        if (!is_array($preferences)) {
            $preferences = [];
        }
        
        return $preferences;
    }

    /**
     * Update notification preferences for a user.
     *
     * @param int   $user_id User ID.
     * @param array $preferences New preferences.
     * @return bool Whether the update was successful.
     */
    public function update_notification_preferences($user_id, $preferences) {
        return update_user_meta($user_id, 'ryvr_notification_preferences', $preferences);
    }

    /**
     * Handle task created event.
     *
     * @param int    $task_id Task ID.
     * @param string $task_type Task type.
     * @param int    $user_id User ID.
     * @return void
     */
    public function on_task_created($task_id, $task_type, $user_id) {
        // Get task data.
        $task_engine = ryvr()->get_component('task_engine');
        $task = $task_engine->get_task($task_id);
        
        if (!$task) {
            return;
        }
        
        // Prepare notification data.
        $data = [
            'task_id' => $task_id,
            'task_title' => $task->title,
            'task_type' => $task_type,
            'task_status' => $task->status,
            'task_url' => admin_url('admin.php?page=ryvr-tasks&task=' . $task_id),
        ];
        
        // Send notification.
        $this->send_notification($user_id, 'task_created', $data);
        
        // If task requires approval, notify admins.
        if ($task->status === 'approval_required') {
            // Get admins.
            $admins = get_users(['role' => 'administrator']);
            
            // Add approval URL.
            $data['approval_url'] = admin_url('admin.php?page=ryvr-tasks&task=' . $task_id . '&action=approve');
            
            // Notify each admin.
            foreach ($admins as $admin) {
                if ($admin->ID !== $user_id) {
                    $this->send_notification($admin->ID, 'task_waiting_approval', $data);
                }
            }
        }
    }

    /**
     * Handle task status changed event.
     *
     * @param int    $task_id Task ID.
     * @param string $old_status Old status.
     * @param string $new_status New status.
     * @return void
     */
    public function on_task_status_changed($task_id, $old_status, $new_status) {
        // Get task data.
        $task_engine = ryvr()->get_component('task_engine');
        $task = $task_engine->get_task($task_id);
        
        if (!$task) {
            return;
        }
        
        // Prepare notification data.
        $data = [
            'task_id' => $task_id,
            'task_title' => $task->title,
            'task_type' => $task->task_type,
            'task_status' => $new_status,
            'task_url' => admin_url('admin.php?page=ryvr-tasks&task=' . $task_id),
            'old_status' => $old_status,
            'new_status' => $new_status,
        ];
        
        // Determine which notification to send based on new status.
        if ($new_status === 'completed') {
            $this->send_notification($task->user_id, 'task_completed', $data);
        } elseif ($new_status === 'failed') {
            // Get the error message from the last log entry.
            $logs = $task->get_logs();
            $error_message = '';
            
            if (!empty($logs)) {
                foreach (array_reverse($logs) as $log) {
                    if ($log->log_level === 'error') {
                        $error_message = $log->message;
                        break;
                    }
                }
            }
            
            $data['error_message'] = $error_message ?: __('An unknown error occurred.', 'ryvr-ai');
            $this->send_notification($task->user_id, 'task_failed', $data);
        } elseif ($new_status === 'approval_required') {
            // Notify admins.
            $admins = get_users(['role' => 'administrator']);
            
            // Add approval URL.
            $data['approval_url'] = admin_url('admin.php?page=ryvr-tasks&task=' . $task_id . '&action=approve');
            
            // Notify each admin.
            foreach ($admins as $admin) {
                if ($admin->ID !== $task->user_id) {
                    $this->send_notification($admin->ID, 'task_waiting_approval', $data);
                }
            }
        } elseif ($old_status === 'approval_required' && $new_status === 'pending') {
            $this->send_notification($task->user_id, 'task_approved', $data);
        }
    }

    /**
     * Handle credits low event.
     *
     * @param int $user_id User ID.
     * @param int $credits_balance Current credits balance.
     * @return void
     */
    public function on_credits_low($user_id, $credits_balance) {
        $data = [
            'credits_balance' => $credits_balance,
            'purchase_url' => admin_url('admin.php?page=ryvr-credits'),
        ];
        
        $this->send_notification($user_id, 'credits_low', $data);
    }

    /**
     * Handle API error event.
     *
     * @param int    $user_id User ID.
     * @param string $api_name API name.
     * @param string $error_message Error message.
     * @return void
     */
    public function on_api_error($user_id, $api_name, $error_message) {
        $data = [
            'api_name' => $api_name,
            'error_message' => $error_message,
        ];
        
        $this->send_notification($user_id, 'api_error', $data);
    }

    /**
     * AJAX handler for getting notifications.
     *
     * @return void
     */
    public function ajax_get_notifications() {
        // Check nonce.
        check_ajax_referer('ryvr_nonce', 'nonce');
        
        // Get parameters.
        $unread_only = isset($_POST['unread_only']) ? (bool) $_POST['unread_only'] : false;
        $limit = isset($_POST['limit']) ? (int) $_POST['limit'] : 10;
        
        // Get notifications.
        $notifications = $this->get_user_notifications(get_current_user_id(), $unread_only, $limit);
        
        // Format notifications for display.
        $formatted = [];
        
        foreach ($notifications as $notification) {
            $data = json_decode($notification->data, true);
            $template_id = $notification->template_id;
            
            if (isset($this->templates[$template_id])) {
                $template = $this->templates[$template_id];
                
                $formatted[] = [
                    'id' => $notification->id,
                    'title' => $this->replace_variables($template['subject'], $data),
                    'message' => $this->replace_variables($template['body'], $data),
                    'read' => (bool) $notification->read,
                    'created_at' => $notification->created_at,
                    'data' => $data,
                ];
            }
        }
        
        wp_send_json_success([
            'notifications' => $formatted,
            'unread_count' => count(array_filter($notifications, function($n) { return !$n->read; })),
        ]);
    }

    /**
     * AJAX handler for marking a notification as read.
     *
     * @return void
     */
    public function ajax_mark_notification_read() {
        // Check nonce.
        check_ajax_referer('ryvr_nonce', 'nonce');
        
        // Get notification ID.
        $notification_id = isset($_POST['notification_id']) ? (int) $_POST['notification_id'] : 0;
        
        if (empty($notification_id)) {
            wp_send_json_error(['message' => __('Invalid notification ID.', 'ryvr-ai')]);
        }
        
        // Mark as read.
        $result = $this->mark_notification_read($notification_id, get_current_user_id());
        
        if (!$result) {
            wp_send_json_error(['message' => __('Failed to mark notification as read.', 'ryvr-ai')]);
        }
        
        wp_send_json_success(['message' => __('Notification marked as read.', 'ryvr-ai')]);
    }

    /**
     * AJAX handler for updating notification preferences.
     *
     * @return void
     */
    public function ajax_update_notification_preferences() {
        // Check nonce.
        check_ajax_referer('ryvr_nonce', 'nonce');
        
        // Get preferences.
        $preferences = isset($_POST['preferences']) ? $_POST['preferences'] : [];
        
        if (!is_array($preferences)) {
            wp_send_json_error(['message' => __('Invalid preferences format.', 'ryvr-ai')]);
        }
        
        // Sanitize preferences.
        $sanitized = [];
        
        foreach ($preferences as $key => $value) {
            $sanitized[sanitize_key($key)] = (bool) $value;
        }
        
        // Update preferences.
        $result = $this->update_notification_preferences(get_current_user_id(), $sanitized);
        
        if (!$result) {
            wp_send_json_error(['message' => __('Failed to update notification preferences.', 'ryvr-ai')]);
        }
        
        wp_send_json_success(['message' => __('Notification preferences updated.', 'ryvr-ai')]);
    }
} 