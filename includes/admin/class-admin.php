<?php
/**
 * The Admin class.
 *
 * Handles admin-related functionality.
 *
 * @package    Ryvr
 * @subpackage Ryvr/Admin
 */

namespace Ryvr\Admin;

/**
 * The Admin class.
 *
 * This class handles admin-related functionality including
 * menu items, settings pages, and admin AJAX handlers.
 *
 * @package    Ryvr
 * @subpackage Ryvr/Admin
 */
class Admin {

    /**
     * Notifications page instance
     *
     * @var Notifications_Page
     */
    private $notifications_page;

    /**
     * Client manager instance
     *
     * @var Client_Manager
     */
    public $client_manager;

    /**
     * Initialize the class.
     *
     * @return void
     */
    public function init() {
        // Include admin classes.
        require_once RYVR_INCLUDES_DIR . 'admin/class-settings.php';
        require_once RYVR_INCLUDES_DIR . 'admin/class-tasks.php';
        require_once RYVR_INCLUDES_DIR . 'admin/class-notifications-page.php';
        require_once RYVR_INCLUDES_DIR . 'admin/class-client-manager.php';
        
        // Initialize notifications page.
        $this->notifications_page = new Notifications_Page();
        $this->notifications_page->init();
        
        // Initialize client manager
        $this->client_manager = new Client_Manager();
        $this->client_manager->init();
        
        // Register hooks.
        $this->register_hooks();
    }

    /**
     * Register WordPress hooks.
     *
     * @return void
     */
    private function register_hooks() {
        // Admin menu.
        add_action( 'admin_menu', [ $this, 'register_admin_menu' ] );
        
        // Admin scripts and styles.
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
        
        // Admin notices.
        add_action( 'admin_notices', [ $this, 'display_admin_notices' ] );
        
        // Plugin action links.
        add_filter( 'plugin_action_links_' . RYVR_PLUGIN_BASENAME, [ $this, 'add_plugin_action_links' ] );
        
        // AJAX handlers
        add_action( 'wp_ajax_ryvr_test_openai_connection', [ $this, 'ajax_test_openai_connection' ] );
        add_action( 'wp_ajax_ryvr_test_dataforseo_connection', [ $this, 'ajax_test_dataforseo_connection' ] );
    }

    /**
     * Register admin menu items.
     *
     * @return void
     */
    public function register_admin_menu() {
        // Main menu.
        add_menu_page(
            __( 'Ryvr AI', 'ryvr-ai' ),
            __( 'Ryvr AI', 'ryvr-ai' ),
            'read',
            'ryvr-ai',
            [ $this, 'render_dashboard_page' ],
            'dashicons-chart-area',
            25
        );

        // Dashboard submenu.
        add_submenu_page(
            'ryvr-ai',
            __( 'Dashboard', 'ryvr-ai' ),
            __( 'Dashboard', 'ryvr-ai' ),
            'read',
            'ryvr-ai',
            [ $this, 'render_dashboard_page' ]
        );

        // Tasks submenu.
        add_submenu_page(
            'ryvr-ai',
            __( 'Tasks', 'ryvr-ai' ),
            __( 'Tasks', 'ryvr-ai' ),
            'edit_posts',
            'ryvr-ai-tasks',
            [ $this, 'render_tasks_page' ]
        );

        // New Task submenu.
        add_submenu_page(
            'ryvr-ai',
            __( 'New Task', 'ryvr-ai' ),
            __( 'New Task', 'ryvr-ai' ),
            'edit_posts',
            'ryvr-ai-new-task',
            [ $this, 'render_new_task_page' ]
        );

        // Credits submenu
        add_submenu_page(
            'ryvr-ai',
            __( 'Credits', 'ryvr-ai' ),
            __( 'Credits', 'ryvr-ai' ),
            'read',
            'ryvr-ai-credits',
            [ $this, 'render_credits_page' ]
        );

        // Clients submenu
        add_submenu_page(
            'ryvr-ai',
            __( 'Clients', 'ryvr-ai' ),
            __( 'Clients', 'ryvr-ai' ),
            'manage_options',
            'edit.php?post_type=ryvr_client',
            null
        );

        // Notifications submenu.
        add_submenu_page(
            'ryvr-ai',
            __( 'Notifications', 'ryvr-ai' ),
            __( 'Notifications', 'ryvr-ai' ),
            'read',
            'ryvr-ai-notifications',
            [ $this, 'render_notifications_page' ]
        );

        // Debug Logs submenu
        add_submenu_page(
            'ryvr-ai',
            __( 'Debug Logs', 'ryvr-ai' ),
            __( 'Debug Logs', 'ryvr-ai' ),
            'manage_options',
            'ryvr-ai-debug',
            [ $this, 'render_debug_page' ]
        );

        // Settings submenu.
        add_submenu_page(
            'ryvr-ai',
            __( 'Settings', 'ryvr-ai' ),
            __( 'Settings', 'ryvr-ai' ),
            'manage_options',
            'ryvr-ai-settings',
            [ $this, 'render_settings_page' ]
        );
    }

    /**
     * Enqueue admin scripts and styles.
     *
     * @param string $hook Current admin page hook.
     * @return void
     */
    public function enqueue_admin_assets( $hook ) {
        // Only load on Ryvr pages.
        if ( strpos( $hook, 'ryvr-ai' ) === false ) {
            return;
        }
        
        // Admin CSS.
        wp_enqueue_style(
            'ryvr-admin',
            RYVR_ASSETS_URL . 'css/admin.css',
            [],
            RYVR_VERSION
        );
        
        // Admin JS.
        wp_enqueue_script(
            'ryvr-admin',
            RYVR_ASSETS_URL . 'js/admin.js',
            [ 'jquery' ],
            RYVR_VERSION,
            true
        );
        
        // Localize script.
        wp_localize_script(
            'ryvr-admin',
            'ryvrData',
            [
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'ryvr_nonce' ),
                'strings' => [
                    'confirmCancel'  => __( 'Are you sure you want to cancel this task?', 'ryvr-ai' ),
                    'confirmApprove' => __( 'Are you sure you want to approve this task?', 'ryvr-ai' ),
                    'taskCreated'    => __( 'Task created successfully.', 'ryvr-ai' ),
                    'taskCancelled'  => __( 'Task cancelled successfully.', 'ryvr-ai' ),
                    'taskApproved'   => __( 'Task approved successfully.', 'ryvr-ai' ),
                    'error'          => __( 'An error occurred.', 'ryvr-ai' ),
                ],
            ]
        );
    }

    /**
     * Display admin notices.
     *
     * @return void
     */
    public function display_admin_notices() {
        // Check for API configuration.
        $screen = get_current_screen();
        
        if ( ! $screen || strpos( $screen->id, 'ryvr-ai' ) === false ) {
            return;
        }
        
        // Get API Manager.
        $api_manager = ryvr()->get_component( 'api_manager' );
        
        if ( ! $api_manager ) {
            return;
        }
        
        // Check OpenAI API configuration.
        $openai_service = $api_manager->get_service( 'openai' );
        
        if ( $openai_service && ! $openai_service->is_configured() && current_user_can( 'manage_options' ) ) {
            ?>
            <div class="notice notice-warning is-dismissible">
                <p>
                    <?php
                    echo wp_kses(
                        sprintf(
                            /* translators: %s: Settings page URL */
                            __( 'OpenAI API key is not configured. <a href="%s">Configure it now</a>.', 'ryvr-ai' ),
                            admin_url( 'admin.php?page=ryvr-ai-settings' )
                        ),
                        [
                            'a' => [
                                'href' => [],
                            ],
                        ]
                    );
                    ?>
                </p>
            </div>
            <?php
        }
        
        // Check DataForSEO API configuration.
        $dataforseo_service = $api_manager->get_service( 'dataforseo' );
        
        if ( $dataforseo_service && ! $dataforseo_service->is_configured() && current_user_can( 'manage_options' ) ) {
            ?>
            <div class="notice notice-warning is-dismissible">
                <p>
                    <?php
                    echo wp_kses(
                        sprintf(
                            /* translators: %s: Settings page URL */
                            __( 'DataForSEO API credentials are not configured. <a href="%s">Configure them now</a>.', 'ryvr-ai' ),
                            admin_url( 'admin.php?page=ryvr-ai-settings' )
                        ),
                        [
                            'a' => [
                                'href' => [],
                            ],
                        ]
                    );
                    ?>
                </p>
            </div>
            <?php
        }
    }

    /**
     * Add plugin action links.
     *
     * @param array $links Default plugin action links.
     * @return array Modified plugin action links.
     */
    public function add_plugin_action_links( $links ) {
        $plugin_links = [
            '<a href="' . admin_url( 'admin.php?page=ryvr-ai-settings' ) . '">' . __( 'Settings', 'ryvr-ai' ) . '</a>',
            '<a href="' . admin_url( 'admin.php?page=ryvr-ai' ) . '">' . __( 'Dashboard', 'ryvr-ai' ) . '</a>',
        ];
        
        return array_merge( $plugin_links, $links );
    }

    /**
     * Render dashboard page.
     *
     * @return void
     */
    public function render_dashboard_page() {
        // Check user permissions
        if (!current_user_can('read')) {
            wp_die(__('Sorry, you are not allowed to access this page.', 'ryvr-ai'));
        }
        require_once RYVR_TEMPLATES_DIR . 'admin/dashboard.php';
    }

    /**
     * Render tasks page.
     *
     * @return void
     */
    public function render_tasks_page() {
        // Check user permissions
        if (!current_user_can('read')) {
            wp_die(__('Sorry, you are not allowed to access this page.', 'ryvr-ai'));
        }
        require_once RYVR_TEMPLATES_DIR . 'admin/tasks.php';
    }

    /**
     * Render new task page.
     *
     * @return void
     */
    public function render_new_task_page() {
        // Check user permissions
        if (!current_user_can('read')) {
            wp_die(__('Sorry, you are not allowed to access this page.', 'ryvr-ai'));
        }
        require_once RYVR_TEMPLATES_DIR . 'admin/new-task.php';
    }

    /**
     * Render credits page.
     *
     * @return void
     */
    public function render_credits_page() {
        // Check user permissions
        if (!current_user_can('read')) {
            wp_die(__('Sorry, you are not allowed to access this page.', 'ryvr-ai'));
        }
        require_once RYVR_TEMPLATES_DIR . 'admin/credits.php';
    }

    /**
     * Render notifications page.
     */
    public function render_notifications_page() {
        // Check if user has permission
        if ( ! current_user_can( 'read' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'ryvr-ai' ) );
        }

        // Include template
        require_once RYVR_TEMPLATES_DIR . 'admin/notifications.php';
    }

    /**
     * Render debug page.
     */
    public function render_debug_page() {
        // Check if user has permission
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'ryvr-ai' ) );
        }

        // Create an instance of the Debug_Page class and call its render_page method
        $debug_page = new Debug_Page();
        $debug_page->render_page();
    }

    /**
     * Render settings page.
     *
     * @return void
     */
    public function render_settings_page() {
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('Sorry, you are not allowed to access this page.', 'ryvr-ai'));
        }
        require_once RYVR_TEMPLATES_DIR . 'admin/settings.php';
    }

    /**
     * AJAX handler for testing OpenAI API connection
     */
    public function ajax_test_openai_connection() {
        // Check nonce
        check_ajax_referer( 'ryvr_test_api', 'nonce' );
        
        // Check permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'ryvr-ai' ) ] );
        }
        
        // Get API key from request
        $api_key = isset( $_POST['api_key'] ) ? sanitize_text_field( $_POST['api_key'] ) : '';
        
        if ( empty( $api_key ) ) {
            wp_send_json_error( [ 'message' => __( 'API key is required.', 'ryvr-ai' ) ] );
            return;
        }
        
        // Get API Manager
        $api_manager = ryvr()->get_component( 'api_manager' );
        
        if ( ! $api_manager ) {
            wp_send_json_error( [ 'message' => __( 'API Manager not available.', 'ryvr-ai' ) ] );
            return;
        }
        
        // Get OpenAI service
        $openai_service = $api_manager->get_service( 'openai' );
        
        if ( ! $openai_service ) {
            wp_send_json_error( [ 'message' => __( 'OpenAI service not available.', 'ryvr-ai' ) ] );
            return;
        }
        
        // Save API key temporarily
        update_option( 'ryvr_openai_api_key', $api_key );
        
        // Test connection
        $result = $openai_service->test_connection();
        
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
            return;
        }
        
        // Return success
        wp_send_json_success( [ 'message' => __( 'Connection successful!', 'ryvr-ai' ) ] );
    }
    
    /**
     * AJAX handler for testing DataForSEO API connection
     */
    public function ajax_test_dataforseo_connection() {
        // Check nonce
        check_ajax_referer( 'ryvr_test_api', 'nonce' );
        
        // Check permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'ryvr-ai' ) ] );
        }
        
        // Get API credentials from request
        $username = isset( $_POST['username'] ) ? sanitize_text_field( $_POST['username'] ) : '';
        $password = isset( $_POST['password'] ) ? sanitize_text_field( $_POST['password'] ) : '';
        
        if ( empty( $username ) || empty( $password ) ) {
            wp_send_json_error( [ 'message' => __( 'Username and password are required.', 'ryvr-ai' ) ] );
            return;
        }
        
        // Get API Manager
        $api_manager = ryvr()->get_component( 'api_manager' );
        
        if ( ! $api_manager ) {
            wp_send_json_error( [ 'message' => __( 'API Manager not available.', 'ryvr-ai' ) ] );
            return;
        }
        
        // Get DataForSEO service
        $dataforseo_service = $api_manager->get_service( 'dataforseo' );
        
        if ( ! $dataforseo_service ) {
            wp_send_json_error( [ 'message' => __( 'DataForSEO service not available.', 'ryvr-ai' ) ] );
            return;
        }
        
        // Save API credentials temporarily
        update_option( 'ryvr_dataforseo_api_login', $username );
        update_option( 'ryvr_dataforseo_api_password', $password );
        
        // Test connection
        $result = $dataforseo_service->test_connection();
        
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
            return;
        }
        
        // Return success
        wp_send_json_success( [ 'message' => __( 'Connection successful!', 'ryvr-ai' ) ] );
    }
} 