<?php
/**
 * The Uninstaller class.
 *
 * Fired during plugin uninstallation.
 *
 * @package    Ryvr
 * @subpackage Ryvr/Core
 */

namespace Ryvr\Core;

/**
 * The Uninstaller class.
 *
 * This class handles all the tasks that need to be performed
 * when the plugin is uninstalled.
 *
 * @package    Ryvr
 * @subpackage Ryvr/Core
 */
class Uninstaller {

    /**
     * Plugin uninstallation tasks.
     *
     * This method is called when the plugin is uninstalled.
     * It removes all plugin data from the database and file system.
     *
     * @return void
     */
    public static function uninstall() {
        // Only proceed if the uninstall was triggered properly.
        if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
            exit;
        }
        
        // Load plugin file to access constants and functions.
        if ( ! class_exists( 'Ryvr_AI_Platform' ) ) {
            include_once plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . 'ryvr-ai-platform.php';
        }
        
        // Remove database tables.
        self::remove_database_tables();
        
        // Remove plugin options.
        self::remove_options();
        
        // Remove plugin files.
        self::remove_plugin_files();
    }

    /**
     * Remove database tables.
     *
     * Removes all database tables created by the plugin.
     *
     * @return void
     */
    private static function remove_database_tables() {
        global $wpdb;
        
        // List of tables to remove.
        $tables = [
            $wpdb->prefix . 'ryvr_tasks',
            $wpdb->prefix . 'ryvr_task_logs',
            $wpdb->prefix . 'ryvr_api_keys',
            $wpdb->prefix . 'ryvr_api_logs',
            $wpdb->prefix . 'ryvr_credits',
        ];
        
        // Drop each table.
        foreach ( $tables as $table ) {
            $wpdb->query( "DROP TABLE IF EXISTS {$table}" );
        }
    }

    /**
     * Remove plugin options.
     *
     * Removes all options created by the plugin.
     *
     * @return void
     */
    private static function remove_options() {
        // List of options to remove.
        $options = [
            'ryvr_version',
            'ryvr_db_version',
            'ryvr_openai_api_key',
            'ryvr_dataforseo_api_login',
            'ryvr_dataforseo_api_password',
            'ryvr_debug_mode',
            'ryvr_log_api_calls',
        ];
        
        // Delete each option.
        foreach ( $options as $option ) {
            delete_option( $option );
        }
        
        // Delete all options with our prefix.
        global $wpdb;
        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'ryvr_%'" );
    }

    /**
     * Remove plugin files.
     *
     * Removes any files created by the plugin outside of its directory.
     *
     * @return void
     */
    private static function remove_plugin_files() {
        // We only need to handle files outside the plugin directory
        // since the plugin directory itself will be removed by WordPress.
        
        // Remove log files.
        if ( defined( 'RYVR_LOGS_DIR' ) && file_exists( RYVR_LOGS_DIR ) ) {
            self::remove_directory( RYVR_LOGS_DIR );
        }
    }

    /**
     * Remove a directory recursively.
     *
     * @param string $dir Directory path.
     * @return bool
     */
    private static function remove_directory( $dir ) {
        if ( ! is_dir( $dir ) ) {
            return false;
        }
        
        $files = array_diff( scandir( $dir ), array( '.', '..' ) );
        
        foreach ( $files as $file ) {
            $path = $dir . '/' . $file;
            
            if ( is_dir( $path ) ) {
                self::remove_directory( $path );
            } else {
                unlink( $path );
            }
        }
        
        return rmdir( $dir );
    }
} 