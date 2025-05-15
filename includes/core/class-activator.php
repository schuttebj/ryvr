<?php
/**
 * The Activator class.
 *
 * Fired during plugin activation.
 *
 * @package    Ryvr
 * @subpackage Ryvr/Core
 */

namespace Ryvr\Core;

/**
 * The Activator class.
 *
 * This class handles all the tasks that need to be performed
 * when the plugin is activated.
 *
 * @package    Ryvr
 * @subpackage Ryvr/Core
 */
class Activator {

    /**
     * Run activation tasks.
     *
     * This method is called when the plugin is activated.
     *
     * @return void
     */
    public static function activate() {
        error_log('Ryvr DEBUG: Plugin activation started');

        self::create_database_tables();
        self::create_admin_user_role();
        self::create_client_user_role();
        
        // Add default options.
        add_option( 'ryvr_db_version', RYVR_DB_VERSION );
        add_option( 'ryvr_log_level', 'info' );
        add_option( 'ryvr_debug_mode', false );
        add_option( 'ryvr_credits_per_task', 5 );
        add_option( 'ryvr_default_task_priority', 50 );
        
        // Flush rewrite rules to enable any custom post types and endpoints.
        flush_rewrite_rules();
        
        error_log('Ryvr DEBUG: Plugin activation completed');
    }

    /**
     * Create database tables.
     *
     * Creates all the database tables needed by the plugin.
     *
     * @return void
     */
    private static function create_database_tables() {
        global $wpdb;
        
        // Instead of loading the entire plugin, directly include the database manager
        require_once RYVR_INCLUDES_DIR . 'database/class-database-manager.php';
        
        // Create an instance directly instead of using the plugin container
        $db_manager = new \Ryvr\Database\Database_Manager();
        $db_manager->init();
        
        // Let the database manager create the tables
        $db_manager->create_tables();
    }

    /**
     * Set default options.
     *
     * Sets up the default options for the plugin.
     *
     * @return void
     */
    private static function set_default_options() {
        // API settings.
        add_option( 'ryvr_openai_api_key', '' );
        add_option( 'ryvr_dataforseo_api_login', '' );
        add_option( 'ryvr_dataforseo_api_password', '' );
        
        // General settings.
        add_option( 'ryvr_debug_mode', 'off' );
        add_option( 'ryvr_log_api_calls', 'off' );
    }

    /**
     * Create necessary directories.
     *
     * Creates directories needed by the plugin.
     *
     * @return void
     */
    private static function create_directories() {
        // Create logs directory if it doesn't exist.
        $logs_dir = RYVR_LOGS_DIR;
        
        if ( ! file_exists( $logs_dir ) ) {
            wp_mkdir_p( $logs_dir );
        }
        
        // Create .htaccess file to protect logs.
        $htaccess_file = $logs_dir . '.htaccess';
        
        if ( ! file_exists( $htaccess_file ) ) {
            $htaccess_content = "# Protect log files\n";
            $htaccess_content .= "<Files ~ \"\.log$\">\n";
            $htaccess_content .= "Order allow,deny\n";
            $htaccess_content .= "Deny from all\n";
            $htaccess_content .= "</Files>\n";
            
            file_put_contents( $htaccess_file, $htaccess_content );
        }
        
        // Create an empty index.php file to prevent directory listing.
        $index_file = $logs_dir . 'index.php';
        
        if ( ! file_exists( $index_file ) ) {
            $index_content = "<?php\n// Silence is golden.";
            file_put_contents( $index_file, $index_content );
        }
    }
} 