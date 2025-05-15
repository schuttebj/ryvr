<?php
/**
 * The Notifications admin page.
 *
 * @package    Ryvr
 * @subpackage Ryvr/Admin
 */

namespace Ryvr\Admin;

/**
 * The Notifications admin page class.
 *
 * This class handles the display and management of user notifications in the admin area.
 *
 * @package    Ryvr
 * @subpackage Ryvr/Admin
 */
class Notifications_Page {

    /**
     * Initialize the class.
     *
     * @return void
     */
    public function init() {
        // Register hooks.
        $this->register_hooks();
    }

    /**
     * Register hooks.
     *
     * @return void
     */
    private function register_hooks() {
        // Add admin menu items.
        add_action('admin_menu', [$this, 'register_menu']);
        
        // Add notification count to menu title.
        add_filter('admin_menu', [$this, 'modify_menu_title']);
        
        // Enqueue scripts and styles.
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        
        // Add notifications indicator to the admin bar.
        add_action('admin_bar_menu', [$this, 'add_notification_indicator'], 100);
        
        // Register AJAX handlers.
        add_action('wp_ajax_ryvr_mark_notification_read', [$this, 'ajax_mark_notification_read']);
        add_action('wp_ajax_ryvr_get_notifications', [$this, 'ajax_get_notifications']);
        add_action('wp_ajax_ryvr_mark_all_notifications_read', [$this, 'ajax_mark_all_notifications_read']);
        add_action('wp_ajax_ryvr_delete_notification', [$this, 'ajax_delete_notification']);
    }

    /**
     * Register admin menu.
     *
     * @return void
     */
    public function register_menu() {
        add_submenu_page(
            'ryvr-dashboard',
            __('Notifications', 'ryvr-ai'),
            __('Notifications', 'ryvr-ai'),
            'edit_posts',
            'ryvr-notifications',
            [$this, 'render_page']
        );
    }

    /**
     * Modify menu title to include unread notification count.
     *
     * @return void
     */
    public function modify_menu_title() {
        global $submenu;
        
        if (!isset($submenu['ryvr-dashboard'])) {
            return;
        }
        
        $unread_count = $this->get_unread_notification_count();
        
        if ($unread_count > 0) {
            foreach ($submenu['ryvr-dashboard'] as $key => $item) {
                if ($item[2] === 'ryvr-notifications') {
                    $submenu['ryvr-dashboard'][$key][0] = sprintf(
                        __('Notifications %s', 'ryvr-ai'),
                        '<span class="update-plugins count-' . $unread_count . '"><span class="plugin-count">' . $unread_count . '</span></span>'
                    );
                    break;
                }
            }
        }
    }

    /**
     * Add notification indicator to the admin bar.
     *
     * @param \WP_Admin_Bar $wp_admin_bar Admin bar object.
     * @return void
     */
    public function add_notification_indicator($wp_admin_bar) {
        $unread_count = $this->get_unread_notification_count();
        
        if ($unread_count < 1) {
            return;
        }
        
        $title = sprintf(
            /* translators: %s: Number of unread notifications */
            _n('%s unread notification', '%s unread notifications', $unread_count, 'ryvr-ai'),
            number_format_i18n($unread_count)
        );
        
        $wp_admin_bar->add_node([
            'id'    => 'ryvr-notifications',
            'title' => '<span class="ab-icon dashicons dashicons-bell"></span><span class="ab-label">' . $unread_count . '</span>',
            'href'  => admin_url('admin.php?page=ryvr-notifications'),
            'meta'  => [
                'title' => $title,
                'class' => 'ryvr-notifications-indicator',
            ],
        ]);
    }

    /**
     * Enqueue scripts and styles.
     *
     * @param string $hook Current admin page.
     * @return void
     */
    public function enqueue_scripts($hook) {
        // Only load on our notifications page.
        if ($hook !== 'ryvr_page_ryvr-notifications') {
            // Still load the notification indicator scripts.
            wp_enqueue_style('ryvr-notification-indicator', RYVR_ASSETS_URL . 'css/notification-indicator.css', [], RYVR_VERSION);
            wp_enqueue_script('ryvr-notification-indicator', RYVR_ASSETS_URL . 'js/notification-indicator.js', ['jquery'], RYVR_VERSION, true);
            
            wp_localize_script('ryvr-notification-indicator', 'rvyrNotifications', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('ryvr_nonce'),
            ]);
            
            return;
        }
        
        // Notifications page scripts and styles.
        wp_enqueue_style('ryvr-notifications', RYVR_ASSETS_URL . 'css/notifications.css', [], RYVR_VERSION);
        wp_enqueue_script('ryvr-notifications', RYVR_ASSETS_URL . 'js/notifications.js', ['jquery'], RYVR_VERSION, true);
        
        wp_localize_script('ryvr-notifications', 'rvyrNotifications', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('ryvr_nonce'),
            'strings' => [
                'markRead'      => __('Mark as Read', 'ryvr-ai'),
                'markUnread'    => __('Mark as Unread', 'ryvr-ai'),
                'delete'        => __('Delete', 'ryvr-ai'),
                'confirmDelete' => __('Are you sure you want to delete this notification?', 'ryvr-ai'),
                'noNotifications' => __('No notifications found.', 'ryvr-ai'),
                'loading'       => __('Loading...', 'ryvr-ai'),
                'error'         => __('An error occurred. Please try again.', 'ryvr-ai'),
            ],
        ]);
    }

    /**
     * Render the notifications page.
     *
     * @return void
     */
    public function render_page() {
        // Get notifications from the database.
        $platform_channel = new \Ryvr\Notifications\Channels\Platform_Channel();
        $notifications = $platform_channel->get_notifications(get_current_user_id(), false, 100);
        
        // Start output.
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
        <?php
    }

    /**
     * Get unread notification count for the current user.
     *
     * @return int
     */
    private function get_unread_notification_count() {
        $platform_channel = new \Ryvr\Notifications\Channels\Platform_Channel();
        return $platform_channel->get_unread_count(get_current_user_id());
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
        $mark_as = isset($_POST['mark_as']) ? $_POST['mark_as'] : 'read';
        
        if (empty($notification_id)) {
            wp_send_json_error(['message' => __('Invalid notification ID.', 'ryvr-ai')]);
        }
        
        // Get platform channel.
        $platform_channel = new \Ryvr\Notifications\Channels\Platform_Channel();
        
        if ($mark_as === 'read') {
            // Mark as read.
            $result = $platform_channel->mark_as_read($notification_id, get_current_user_id());
        } else {
            // Mark as unread (uses the same function with read=0).
            global $wpdb;
            $table_name = $wpdb->prefix . 'ryvr_platform_notifications';
            $result = $wpdb->update(
                $table_name,
                ['read' => 0],
                [
                    'id' => $notification_id,
                    'user_id' => get_current_user_id(),
                ],
                ['%d'],
                ['%d', '%d']
            );
            $result = $result !== false;
        }
        
        if (!$result) {
            wp_send_json_error(['message' => __('Failed to update notification.', 'ryvr-ai')]);
        }
        
        wp_send_json_success([
            'message' => __('Notification updated.', 'ryvr-ai'),
            'unread_count' => $platform_channel->get_unread_count(get_current_user_id()),
        ]);
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
        $limit = isset($_POST['limit']) ? (int) $_POST['limit'] : 100;
        
        // Get platform channel.
        $platform_channel = new \Ryvr\Notifications\Channels\Platform_Channel();
        
        // Get notifications.
        $notifications = $platform_channel->get_notifications(get_current_user_id(), $unread_only, $limit);
        
        // Format notifications for output.
        $formatted = [];
        
        foreach ($notifications as $notification) {
            $formatted[] = [
                'id' => $notification->id,
                'title' => $notification->title,
                'message' => $notification->message,
                'data' => $notification->data,
                'read' => (bool) $notification->read,
                'created_at' => $notification->created_at,
                'created_at_human' => human_time_diff(strtotime($notification->created_at), current_time('timestamp')) . ' ' . __('ago', 'ryvr-ai'),
            ];
        }
        
        wp_send_json_success([
            'notifications' => $formatted,
            'unread_count' => $platform_channel->get_unread_count(get_current_user_id()),
        ]);
    }

    /**
     * AJAX handler for marking all notifications as read.
     *
     * @return void
     */
    public function ajax_mark_all_notifications_read() {
        // Check nonce.
        check_ajax_referer('ryvr_nonce', 'nonce');
        
        // Get platform channel.
        $platform_channel = new \Ryvr\Notifications\Channels\Platform_Channel();
        
        // Mark all as read.
        $result = $platform_channel->mark_all_as_read(get_current_user_id());
        
        if (!$result) {
            wp_send_json_error(['message' => __('Failed to mark notifications as read.', 'ryvr-ai')]);
        }
        
        wp_send_json_success([
            'message' => __('All notifications marked as read.', 'ryvr-ai'),
            'unread_count' => 0,
        ]);
    }

    /**
     * AJAX handler for deleting a notification.
     *
     * @return void
     */
    public function ajax_delete_notification() {
        // Check nonce.
        check_ajax_referer('ryvr_nonce', 'nonce');
        
        // Get notification ID.
        $notification_id = isset($_POST['notification_id']) ? (int) $_POST['notification_id'] : 0;
        
        if (empty($notification_id)) {
            wp_send_json_error(['message' => __('Invalid notification ID.', 'ryvr-ai')]);
        }
        
        // Get platform channel.
        $platform_channel = new \Ryvr\Notifications\Channels\Platform_Channel();
        
        // Delete notification.
        $result = $platform_channel->delete_notification($notification_id, get_current_user_id());
        
        if (!$result) {
            wp_send_json_error(['message' => __('Failed to delete notification.', 'ryvr-ai')]);
        }
        
        wp_send_json_success([
            'message' => __('Notification deleted.', 'ryvr-ai'),
            'unread_count' => $platform_channel->get_unread_count(get_current_user_id()),
        ]);
    }
} 