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
     * Initialize the class.
     *
     * @return void
     */
    public function init() {
        // Include admin classes.
        require_once RYVR_INCLUDES_DIR . 'admin/class-settings.php';
        require_once RYVR_INCLUDES_DIR . 'admin/class-tasks.php';
        require_once RYVR_INCLUDES_DIR . 'admin/class-notifications-page.php';
        
        // Initialize notifications page.
        $this->notifications_page = new Notifications_Page();
        $this->notifications_page->init();
        
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
    }

    /**
     * Register admin menu items.
     *
     * @return void
     */
    public function register_admin_menu() {
        // Debug output to check if this method is being called
        error_log('Ryvr DEBUG: Admin::register_admin_menu() called');
        
        // Debug output for current user capabilities
        $current_user = wp_get_current_user();
        error_log('Ryvr DEBUG: Current user ID: ' . $current_user->ID);
        error_log('Ryvr DEBUG: Current user login: ' . $current_user->user_login);
        error_log('Ryvr DEBUG: Current user has edit_posts: ' . (current_user_can('edit_posts') ? 'yes' : 'no'));
        error_log('Ryvr DEBUG: Current user has manage_options: ' . (current_user_can('manage_options') ? 'yes' : 'no'));
        
        // Main menu item.
        add_menu_page(
            __( 'Ryvr AI Platform', 'ryvr-ai' ),
            __( 'Ryvr AI', 'ryvr-ai' ),
            'read',
            'ryvr-ai',
            [ $this, 'render_dashboard_page' ],
            'dashicons-chart-area',
            30
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
            'read',
            'ryvr-ai-tasks',
            [ $this, 'render_tasks_page' ]
        );
        
        // New Task submenu.
        add_submenu_page(
            'ryvr-ai',
            __( 'New Task', 'ryvr-ai' ),
            __( 'New Task', 'ryvr-ai' ),
            'read',
            'ryvr-ai-new-task',
            [ $this, 'render_new_task_page' ]
        );
        
        // Credits submenu.
        add_submenu_page(
            'ryvr-ai',
            __( 'Credits', 'ryvr-ai' ),
            __( 'Credits', 'ryvr-ai' ),
            'read',
            'ryvr-ai-credits',
            [ $this, 'render_credits_page' ]
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
        require_once RYVR_TEMPLATES_DIR . 'admin/dashboard.php';
    }

    /**
     * Render tasks page.
     *
     * @return void
     */
    public function render_tasks_page() {
        require_once RYVR_TEMPLATES_DIR . 'admin/tasks.php';
    }

    /**
     * Render new task page.
     *
     * @return void
     */
    public function render_new_task_page() {
        require_once RYVR_TEMPLATES_DIR . 'admin/new-task.php';
    }

    /**
     * Render credits page.
     *
     * @return void
     */
    public function render_credits_page() {
        require_once RYVR_TEMPLATES_DIR . 'admin/credits.php';
    }

    /**
     * Render settings page.
     *
     * @return void
     */
    public function render_settings_page() {
        require_once RYVR_TEMPLATES_DIR . 'admin/settings.php';
    }
} 