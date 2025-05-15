<?php
/**
 * Plugin Name: Ryvr AI Platform
 * Plugin URI: https://ryvr.ai
 * Description: Digital agency automation platform leveraging OpenAI and DataForSEO APIs to automate SEO, PPC, and content tasks.
 * Version: 1.0.0
 * Author: Ryvr
 * Author URI: https://ryvr.ai
 * Text Domain: ryvr-ai
 * Domain Path: /languages
 * Requires at least: 5.9
 * Requires PHP: 7.4
 *
 * @package Ryvr
 */

// Increase memory limit
ini_set('memory_limit', '1024M');

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Check if Complianz is active
function ryvr_check_complianz_dependency() {
    // Include plugin.php to use is_plugin_active
    if ( ! function_exists( 'is_plugin_active' ) ) {
        include_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    
    if ( ! is_plugin_active( 'complianz-gdpr/complianz-gdpr.php' ) ) {
        add_action( 'admin_notices', 'ryvr_complianz_missing_notice' );
    }
}
add_action( 'admin_init', 'ryvr_check_complianz_dependency' );

// Admin notice for missing Complianz
function ryvr_complianz_missing_notice() {
    if ( current_user_can( 'activate_plugins' ) ) {
        ?>
        <div class="notice notice-warning is-dismissible">
            <p><?php _e( 'Ryvr AI Platform recommends Complianz GDPR/CCPA for complete compliance with privacy regulations. <a href="plugin-install.php?s=complianz&tab=search&type=term">Install Complianz</a> for full GDPR compliance.', 'ryvr-ai' ); ?></p>
        </div>
        <?php
    }
}

// Define plugin constants.
define( 'RYVR_VERSION', '1.0.0' );
define( 'RYVR_DB_VERSION', '1.0.0' );
define( 'RYVR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'RYVR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'RYVR_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'RYVR_INCLUDES_DIR', RYVR_PLUGIN_DIR . 'includes/' );
define( 'RYVR_ASSETS_DIR', RYVR_PLUGIN_DIR . 'assets/' );
define( 'RYVR_ASSETS_URL', RYVR_PLUGIN_URL . 'assets/' );
define( 'RYVR_TEMPLATES_DIR', RYVR_PLUGIN_DIR . 'templates/' );
define( 'RYVR_LOGS_DIR', RYVR_PLUGIN_DIR . 'logs/' );

// Define debug constants
define( 'RYVR_DEBUG', true );  // Enable debugging
define( 'RYVR_DEBUG_TRACE', true );  // Enable stack traces for error logs

/**
 * Class Ryvr_AI_Platform
 *
 * Main plugin class that bootstraps the entire platform.
 */
final class Ryvr_AI_Platform {

    /**
     * Plugin instance.
     *
     * @var Ryvr_AI_Platform
     */
    private static $instance = null;

    /**
     * Plugin components.
     *
     * @var array
     */
    private $components = [];

    /**
     * Get the singleton instance.
     *
     * @return Ryvr_AI_Platform
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        // Initialize the plugin.
        $this->init();

        // Register activation and deactivation hooks.
        register_activation_hook( __FILE__, [ $this, 'activate' ] );
        register_deactivation_hook( __FILE__, [ $this, 'deactivate' ] );
    }

    /**
     * Initialize the plugin.
     *
     * @return void
     */
    private function init() {
        // Load plugin files.
        $this->load_files();

        // Initialize components.
        $this->init_components();

        // Register hooks.
        $this->register_hooks();
    }

    /**
     * Load plugin files.
     *
     * @return void
     */
    private function load_files() {
        // Load core files.
        require_once RYVR_INCLUDES_DIR . 'core/functions.php';
        require_once RYVR_INCLUDES_DIR . 'core/class-loader.php';
        require_once RYVR_INCLUDES_DIR . 'core/class-i18n.php';
        require_once RYVR_INCLUDES_DIR . 'core/class-activator.php';
        require_once RYVR_INCLUDES_DIR . 'core/class-deactivator.php';
        require_once RYVR_INCLUDES_DIR . 'core/debug.php';

        // Load API files.
        require_once RYVR_INCLUDES_DIR . 'api/class-api-manager.php';

        // Load Task Engine files.
        require_once RYVR_INCLUDES_DIR . 'task-engine/class-task-engine.php';

        // Load Admin files.
        require_once RYVR_INCLUDES_DIR . 'admin/class-admin.php';
        require_once RYVR_INCLUDES_DIR . 'admin/class-debug-page.php';

        $this->admin = new \Ryvr\Admin\Admin();
        $this->components['admin'] = $this->admin;
        $this->admin->init();
        
        // Initialize debug page
        $this->debug_page = new \Ryvr\Admin\Debug_Page();
        $this->components['debug_page'] = $this->debug_page;
        $this->debug_page->init();

        // Load client manager
        require_once RYVR_INCLUDES_DIR . 'admin/class-client-manager.php';
        $this->client_manager = new \Ryvr\Admin\Client_Manager();
        $this->components['client_manager'] = $this->client_manager;
        $this->client_manager->init();

        // Load Database files.
        require_once RYVR_INCLUDES_DIR . 'database/class-database-manager.php';
        
        // Load Notification files.
        require_once RYVR_INCLUDES_DIR . 'notifications/class-notification-manager.php';
        
        // Load Benchmark files.
        require_once RYVR_INCLUDES_DIR . 'class-benchmark-manager.php';
    }

    /**
     * Initialize components.
     *
     * @return void
     */
    private function init_components() {
        // Create components.
        $this->components['loader'] = new Ryvr\Core\Loader();
        $this->components['i18n'] = new Ryvr\Core\I18n();
        $this->components['api_manager'] = new Ryvr\API\API_Manager();
        $this->components['task_engine'] = new Ryvr\Task_Engine\Task_Engine();
        $this->components['admin'] = new Ryvr\Admin\Admin();
        $this->components['database_manager'] = new Ryvr\Database\Database_Manager();
        $this->components['notification_manager'] = new Ryvr\Notifications\Notification_Manager();
        $this->components['benchmark_manager'] = new Ryvr\Benchmarks\Benchmark_Manager();

        // Initialize components.
        $this->components['i18n']->init();
        $this->components['api_manager']->init();
        $this->components['task_engine']->init();
        $this->components['admin']->init();
        $this->components['database_manager']->init();
        $this->components['notification_manager']->init();
        
        // Initialize benchmark manager with dependencies
        $this->components['benchmark_manager']->init(
            $this->components['database_manager'],
            $this->components['api_manager']->get_service('dataforseo')
        );
    }

    /**
     * Register hooks.
     *
     * @return void
     */
    private function register_hooks() {
        // Core WordPress hooks.
        add_action( 'plugins_loaded', [ $this, 'load_plugin_textdomain' ] );

        // Run loader.
        $this->components['loader']->run();
    }

    /**
     * Load the plugin text domain for translation.
     *
     * @return void
     */
    public function load_plugin_textdomain() {
        load_plugin_textdomain( 'ryvr-ai', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    }

    /**
     * Plugin activation.
     *
     * @return void
     */
    public function activate() {
        // Run activator.
        Ryvr\Core\Activator::activate();
    }

    /**
     * Plugin deactivation.
     *
     * @return void
     */
    public function deactivate() {
        // Run deactivator.
        Ryvr\Core\Deactivator::deactivate();
    }

    /**
     * Get a component instance.
     *
     * @param string $component Component name.
     * @return object|null Component instance or null if not found.
     */
    public function get_component( $component ) {
        return isset( $this->components[ $component ] ) ? $this->components[ $component ] : null;
    }

    /**
     * Get all components.
     *
     * @return array All components.
     */
    public function get_components() {
        return $this->components;
    }

    /**
     * Prevent cloning.
     *
     * @return void
     */
    private function __clone() {
        // Prevent cloning.
    }

    /**
     * Prevent unserializing.
     *
     * @return void
     */
    public function __wakeup() {
        // Prevent unserializing.
    }
}

/**
 * Returns the main instance of the Ryvr AI Platform.
 *
 * @return Ryvr_AI_Platform
 */
function ryvr() {
    return Ryvr_AI_Platform::get_instance();
}

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/core/class-activator.php
 */
function activate_ryvr_ai_platform() {
    // Only load the activator class to reduce memory usage during activation
    require_once plugin_dir_path( __FILE__ ) . 'includes/core/class-activator.php';
    \Ryvr\Core\Activator::activate();
}

/**
 * The code that runs during plugin initialization.
 */
function init_ryvr_ai_platform() {
    // Include database manager file first
    require_once plugin_dir_path( __FILE__ ) . 'includes/database/class-database-manager.php';
    
    // Check if database needs update
    $db_manager = new \Ryvr\Database\Database_Manager();
    $db_manager->init(); // Initialize the database manager first
    if ($db_manager->needs_update()) {
        $db_manager->update();
    }
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/core/class-deactivator.php
 */
function deactivate_ryvr_ai_platform() {
    require_once plugin_dir_path( __FILE__ ) . 'includes/core/class-deactivator.php';
    \Ryvr\Core\Deactivator::deactivate();
}

/**
 * The code that runs the main plugin functionality.
 */
function run_ryvr_ai_platform() {
    // Initialize the main plugin class
    ryvr();
}

// Register activation, deactivation, and uninstallation hooks
register_activation_hook( __FILE__, 'activate_ryvr_ai_platform' );
register_deactivation_hook( __FILE__, 'deactivate_ryvr_ai_platform' );

// Initialize the plugin - run database update first, then initialize the main plugin
add_action('plugins_loaded', 'init_ryvr_ai_platform', 5); // Lower priority number means it runs earlier
add_action('plugins_loaded', 'run_ryvr_ai_platform', 10); // Default priority 