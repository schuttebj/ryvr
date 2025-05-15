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
     * Plugin activation tasks.
     *
     * This method is called when the plugin is activated.
     * It creates the necessary database tables, sets up default options,
     * and performs other activation tasks.
     *
     * @return void
     */
    public static function activate() {
        // Create database tables.
        self::create_database_tables();

        // Set up default options.
        self::set_default_options();

        // Set version.
        update_option( 'ryvr_version', RYVR_VERSION );
        update_option( 'ryvr_db_version', RYVR_DB_VERSION );

        // Create necessary directories.
        self::create_directories();

        // Flush rewrite rules.
        flush_rewrite_rules();
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

        $charset_collate = $wpdb->get_charset_collate();

        // Get the database manager instance.
        $db_manager = ryvr()->get_component( 'db_manager' );
        
        // If the database manager is not available, return early.
        if ( ! $db_manager ) {
            return;
        }
        
        // Let the database manager create the tables.
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