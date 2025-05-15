<?php
/**
 * Plugin Name: Ryvr AI Platform
 * Plugin URI: https://ryvr.ai
 * Description: Digital agency automation platform leveraging OpenAI and DataForSEO APIs to automate SEO, PPC, and content tasks.
 * Version: 1.0.11
 * Author: Ryvr
 * Author URI: https://ryvr.ai
 * Text Domain: ryvr-ai
 * Domain Path: /languages
 * Requires at least: 5.9
 * Requires PHP: 7.4
 *
 * @package Ryvr
 */

// Increase memory limit - try maximum allowed
ini_set('memory_limit', '1024M');

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants.
define( 'RYVR_VERSION', '1.0.11' );
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

// Check if Complianz is active - move this to init to avoid too early loading
function ryvr_check_complianz_dependency() {
    // Include plugin.php to use is_plugin_active
    if ( ! function_exists( 'is_plugin_active' ) ) {
        include_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    
    if ( ! is_plugin_active( 'complianz-gdpr/complianz-gdpr.php' ) ) {
        add_action( 'admin_notices', 'ryvr_complianz_missing_notice' );
    }
}
// Move to init instead of admin_init to ensure it runs at the right time
add_action( 'init', 'ryvr_check_complianz_dependency', 20 );

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

/**
 * Load plugin textdomain properly at the right time.
 * Moving this to the init hook with priority 1 to ensure it loads at the right time.
 */
function ryvr_load_textdomain() {
    load_plugin_textdomain( 'ryvr-ai', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'ryvr_load_textdomain', 1 );
add_action( 'plugins_loaded', 'ryvr_load_textdomain', 5 );

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
        // Load core files only - essential for functioning
        $this->load_core_files();

        // Initialize essential components - defer others until needed
        $this->init_essential_components();
        
        // Initialize admin components directly if in admin area
        if (is_admin()) {
            $this->init_admin_components();
        }

        // Register hooks.
        $this->register_hooks();
    }

    /**
     * Load core files that are always needed.
     *
     * @return void
     */
    private function load_core_files() {
        // Load core functionality only
        require_once RYVR_INCLUDES_DIR . 'core/functions.php';
        require_once RYVR_INCLUDES_DIR . 'core/class-loader.php';
        require_once RYVR_INCLUDES_DIR . 'core/class-i18n.php';
        require_once RYVR_INCLUDES_DIR . 'core/class-activator.php';
        require_once RYVR_INCLUDES_DIR . 'core/class-deactivator.php';
        require_once RYVR_INCLUDES_DIR . 'core/debug.php';

        // Load Database files (needed for basic functionality)
        require_once RYVR_INCLUDES_DIR . 'database/class-database-manager.php';
    }
    
    /**
     * Initialize essential components that are always needed.
     *
     * @return void
     */
    private function init_essential_components() {
        // Create only essential components
        $this->components['loader'] = new Ryvr\Core\Loader();
        $this->components['i18n'] = new Ryvr\Core\I18n();
        $this->components['database_manager'] = new Ryvr\Database\Database_Manager();
        
        // Initialize only essential components
        $this->components['database_manager']->init();
        
        // Don't initialize i18n here - we're using the separate function for textdomain loading
    }
    
    /**
     * Initialize admin components.
     * This is deferred until admin_init to reduce memory usage on frontend requests.
     *
     * @return void
     */
    public function init_admin_components() {
        // Monitor memory usage and adjust if needed
        $memory_limit = ini_get('memory_limit');
        $memory_limit_bytes = $this->return_bytes($memory_limit);
        
        // If memory limit is low, try to increase it
        if ($memory_limit_bytes < 256 * 1024 * 1024) {
            ini_set('memory_limit', '256M');
        }
        
        // Register error handler for memory exhaustion
        register_shutdown_function(function() {
            $error = error_get_last();
            if ($error && strpos($error['message'], 'Allowed memory size') !== false) {
                error_log('Ryvr ERROR: Memory exhaustion detected. Current limit: ' . ini_get('memory_limit'));
                gc_collect_cycles(); // Force garbage collection
            }
        });
        
        // Load only essential classes first
        require_once RYVR_INCLUDES_DIR . 'api/class-api-manager.php';
        require_once RYVR_INCLUDES_DIR . 'api/class-api-service.php';
        require_once RYVR_INCLUDES_DIR . 'api/abstract-class-api-service.php';
        require_once RYVR_INCLUDES_DIR . 'api/class-api-cache.php';
        require_once RYVR_INCLUDES_DIR . 'admin/class-admin.php';
        
        // Initialize only essential components (API and Admin)
        $this->components['api_manager'] = new Ryvr\API\API_Manager();
        $this->components['admin'] = new \Ryvr\Admin\Admin();
        
        // Initialize these essential components
        $this->components['api_manager']->init();
        $this->components['admin']->init();
        
        // Defer loading other components to admin_init 
        add_action('admin_init', function() {
            // Load additional required files on demand
            require_once RYVR_INCLUDES_DIR . 'task-engine/class-task-engine.php';
            require_once RYVR_INCLUDES_DIR . 'admin/class-debug-page.php';
            require_once RYVR_INCLUDES_DIR . 'admin/class-client-manager.php';
            require_once RYVR_INCLUDES_DIR . 'notifications/class-notification-manager.php';
            require_once RYVR_INCLUDES_DIR . 'notifications/channels/class-platform-channel.php';
            require_once RYVR_INCLUDES_DIR . 'class-benchmark-manager.php';
            
            // Now load API service files
            require_once RYVR_INCLUDES_DIR . 'api/services/class-dataforseo-service.php';
            require_once RYVR_INCLUDES_DIR . 'api/class-dataforseo-service-adapter.php';
            require_once RYVR_INCLUDES_DIR . 'api/services/class-openai-service.php';
            
            // Initialize remaining components only if they haven't been initialized yet
            if (!isset($this->components['task_engine'])) {
                $this->components['task_engine'] = new Ryvr\Task_Engine\Task_Engine();
                $this->components['task_engine']->init();
            }
            
            if (!isset($this->components['debug_page'])) {
                $this->components['debug_page'] = new \Ryvr\Admin\Debug_Page();
                $this->components['debug_page']->init();
            }
            
            if (!isset($this->components['client_manager'])) {
                $this->components['client_manager'] = new \Ryvr\Admin\Client_Manager();
                $this->components['client_manager']->init();
            }
            
            if (!isset($this->components['notification_manager'])) {
                $this->components['notification_manager'] = new Ryvr\Notifications\Notification_Manager();
                $this->components['notification_manager']->init();
            }
            
            if (!isset($this->components['benchmark_manager'])) {
                $this->components['benchmark_manager'] = new Ryvr\Benchmarks\Benchmark_Manager();
                $this->components['benchmark_manager']->init(
                    $this->components['database_manager'],
                    $this->components['api_manager']->get_service('dataforseo')
                );
            }
        }, 20); // Lower priority to ensure it runs after other admin_init functions
    }

    /**
     * Register hooks.
     *
     * @return void
     */
    private function register_hooks() {
        // Run loader.
        $this->components['loader']->run();
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

    /**
     * Helper function to convert memory limit strings to bytes
     * 
     * @param string $size_str Memory size string (e.g., '256M')
     * @return int Size in bytes
     */
    private function return_bytes($size_str) {
        $val = trim($size_str);
        $last = strtolower($val[strlen($val)-1]);
        $val = (int)$val;
        
        switch($last) {
            case 'g': $val *= 1024;
            case 'm': $val *= 1024;
            case 'k': $val *= 1024;
        }
        
        return $val;
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
 * This is split into two parts - essential database updates happen first,
 * and full plugin initialization happens after.
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
 * This is intentionally kept as lightweight as possible.
 */
function run_ryvr_ai_platform() {
    // Initialize the main plugin class - this will set up lazy loading
    ryvr();
}

/**
 * Initialize admin class early for menu registration
 */
function ryvr_init_admin_early() {
    error_log('Ryvr DEBUG: Early admin initialization function called');
    
    // Load all required files
    require_once RYVR_INCLUDES_DIR . 'admin/class-admin.php';
    require_once RYVR_INCLUDES_DIR . 'admin/class-settings.php';
    require_once RYVR_INCLUDES_DIR . 'admin/class-tasks.php';
    require_once RYVR_INCLUDES_DIR . 'admin/class-notifications-page.php';
    require_once RYVR_INCLUDES_DIR . 'admin/class-debug-page.php';
    require_once RYVR_INCLUDES_DIR . 'admin/class-client-manager.php';
    require_once RYVR_INCLUDES_DIR . 'notifications/class-notification-manager.php';
    require_once RYVR_INCLUDES_DIR . 'notifications/channels/class-platform-channel.php';
    require_once RYVR_INCLUDES_DIR . 'class-benchmark-manager.php';
    
    // Initialize admin directly
    try {
        $admin = new \Ryvr\Admin\Admin();
        $admin->init();
        error_log('Ryvr DEBUG: Admin class initialized directly');
    } catch (Exception $e) {
        error_log('Ryvr DEBUG: Error initializing Admin class: ' . $e->getMessage());
    }
}

/**
 * Add a direct debug menu item for debugging (to see if admin menu works at all)
 */
function ryvr_add_direct_debug_menu() {
    error_log('Ryvr DEBUG: Direct menu registration function called');
    
    // Check if admin class is available
    if (class_exists('\\Ryvr\\Admin\\Admin')) {
        error_log('Ryvr DEBUG: Admin class exists');
    } else {
        error_log('Ryvr DEBUG: Admin class does NOT exist');
    }
    
    // Add a direct menu item
    add_menu_page(
        'Ryvr Debug',
        'Ryvr Debug',
        'read',
        'ryvr-debug',
        'ryvr_render_debug_page',
        'dashicons-buddicons-activity',
        35
    );
}

// Add our direct debug menu - keep this one for debugging
// add_action('admin_menu', 'ryvr_add_direct_debug_menu');

// Restore early admin initialization
// add_action('admin_menu', 'ryvr_init_admin_early', 5); // Priority 5 to run before normal menu items

// Register activation, deactivation, and uninstallation hooks
register_activation_hook( __FILE__, 'activate_ryvr_ai_platform' );
register_deactivation_hook( __FILE__, 'deactivate_ryvr_ai_platform' );

// Initialize the plugin - run database update first, then initialize the main plugin
// Using higher priority numbers to ensure it runs after other plugins
add_action('plugins_loaded', 'init_ryvr_ai_platform', 15); 
add_action('plugins_loaded', 'run_ryvr_ai_platform', 20); 