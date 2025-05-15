<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @package    Ryvr
 * @subpackage Ryvr/Admin
 */

namespace Ryvr\Admin;

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and hooks for the admin area.
 *
 * @package    Ryvr
 * @subpackage Ryvr/Admin
 */
class Admin {

    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $plugin_name    The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $version    The current version of this plugin.
     */
    private $version;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param    string    $plugin_name       The name of this plugin.
     * @param    string    $version           The version of this plugin.
     */
    public function __construct( $plugin_name, $version ) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     * Register the hooks related to the admin area functionality.
     *
     * @since    1.0.0
     * @return   void
     */
    public function register_hooks() {
        // Add admin menu items
        add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
        
        // Register admin styles and scripts
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
    }

    /**
     * Register the admin menu items.
     *
     * @since    1.0.0
     * @return   void
     */
    public function register_admin_menu() {
        // Main menu item
        add_menu_page(
            'Ryvr AI Platform',
            'Ryvr AI',
            'manage_options',
            'ryvr-ai-platform',
            array( $this, 'display_dashboard_page' ),
            'dashicons-chart-area',
            30
        );
        
        // Dashboard submenu
        add_submenu_page(
            'ryvr-ai-platform',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'ryvr-ai-platform',
            array( $this, 'display_dashboard_page' )
        );
        
        // API Integration Demo submenu
        add_submenu_page(
            'ryvr-ai-platform',
            'API Integration Demo',
            'API Demo',
            'manage_options',
            'ryvr-api-demo',
            array( $this, 'display_api_demo_page' )
        );
        
        // Settings submenu
        add_submenu_page(
            'ryvr-ai-platform',
            'Ryvr Settings',
            'Settings',
            'manage_options',
            'ryvr-settings',
            array( $this, 'display_settings_page' )
        );
    }

    /**
     * Enqueue admin-specific styles.
     *
     * @since    1.0.0
     * @param    string    $hook_suffix    The current admin page.
     * @return   void
     */
    public function enqueue_styles( $hook_suffix ) {
        // Only load styles on plugin pages
        if ( strpos( $hook_suffix, 'ryvr-' ) === false ) {
            return;
        }
        
        wp_enqueue_style(
            $this->plugin_name,
            plugin_dir_url( __FILE__ ) . 'css/ryvr-admin.css',
            array(),
            $this->version,
            'all'
        );
    }

    /**
     * Enqueue admin-specific scripts.
     *
     * @since    1.0.0
     * @param    string    $hook_suffix    The current admin page.
     * @return   void
     */
    public function enqueue_scripts( $hook_suffix ) {
        // Only load scripts on plugin pages
        if ( strpos( $hook_suffix, 'ryvr-' ) === false ) {
            return;
        }
        
        wp_enqueue_script(
            $this->plugin_name,
            plugin_dir_url( __FILE__ ) . 'js/ryvr-admin.js',
            array( 'jquery' ),
            $this->version,
            false
        );
    }

    /**
     * Display the dashboard page.
     *
     * @since    1.0.0
     * @return   void
     */
    public function display_dashboard_page() {
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/partials/dashboard.php';
    }

    /**
     * Display the API integration demo page.
     *
     * @since    1.0.0
     * @return   void
     */
    public function display_api_demo_page() {
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/partials/api-integration-demo.php';
    }

    /**
     * Display the settings page.
     *
     * @since    1.0.0
     * @return   void
     */
    public function display_settings_page() {
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/partials/settings.php';
    }
} 