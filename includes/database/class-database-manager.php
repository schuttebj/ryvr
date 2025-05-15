<?php
/**
 * The Database Manager class.
 *
 * Handles all database operations for the plugin.
 *
 * @package    Ryvr
 * @subpackage Ryvr/Database
 */

namespace Ryvr\Database;

/**
 * The Database Manager class.
 *
 * This class handles database table creation, updates, and migrations.
 * It also provides utility methods for database operations.
 *
 * @package    Ryvr
 * @subpackage Ryvr/Database
 */
class Database_Manager {

    /**
     * Table names.
     *
     * @var array
     */
    private $tables = [];

    /**
     * Initialize the class.
     *
     * @return void
     */
    public function init() {
        global $wpdb;
        
        // Set table names.
        $this->tables = [
            'tasks'     => $wpdb->prefix . 'ryvr_tasks',
            'task_logs' => $wpdb->prefix . 'ryvr_task_logs',
            'api_keys'  => $wpdb->prefix . 'ryvr_api_keys',
            'api_logs'  => $wpdb->prefix . 'ryvr_api_logs',
            'credits'   => $wpdb->prefix . 'ryvr_credits',
            'sessions'  => $wpdb->prefix . 'ryvr_sessions',
            'notifications' => $wpdb->prefix . 'ryvr_notifications',
            'platform_notifications' => $wpdb->prefix . 'ryvr_platform_notifications',
            'benchmarks' => $wpdb->prefix . 'ryvr_benchmarks',
        ];
        
        // Check if database needs update.
        add_action( 'plugins_loaded', [ $this, 'check_database_version' ] );
    }

    /**
     * Check if database needs update.
     *
     * @return void
     */
    public function check_database_version() {
        $db_version = get_option( 'ryvr_db_version', '0' );
        
        if ( $db_version !== RYVR_DB_VERSION ) {
            $this->update_database();
        }
    }

    /**
     * Update database to latest version.
     *
     * @return void
     */
    public function update_database() {
        $db_version = get_option( 'ryvr_db_version', '0' );
        
        // Create tables if they don't exist.
        $this->create_tables();
        
        // Apply migrations.
        $this->run_migrations( $db_version );
        
        // Update database version.
        update_option( 'ryvr_db_version', RYVR_DB_VERSION );
    }

    /**
     * Create database tables.
     *
     * @return void
     */
    public function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Include the dbDelta function.
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        
        // Tasks table.
        $sql = "CREATE TABLE {$this->tables['tasks']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            task_type varchar(50) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            title varchar(255) NOT NULL,
            description longtext NULL,
            inputs longtext NULL,
            outputs longtext NULL,
            credits_cost int(11) NOT NULL DEFAULT 0,
            priority int(11) NOT NULL DEFAULT 50,
            dependencies longtext NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            started_at datetime NULL,
            completed_at datetime NULL,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY status (status),
            KEY task_type (task_type),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        dbDelta( $sql );
        
        // Task logs table.
        $sql = "CREATE TABLE {$this->tables['task_logs']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            task_id bigint(20) unsigned NOT NULL,
            message text NOT NULL,
            log_level varchar(20) NOT NULL DEFAULT 'info',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY task_id (task_id),
            KEY log_level (log_level),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        dbDelta( $sql );
        
        // API keys table.
        $sql = "CREATE TABLE {$this->tables['api_keys']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            service varchar(50) NOT NULL,
            api_key varchar(255) NOT NULL,
            api_secret varchar(255) NULL,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY service (service),
            KEY is_active (is_active)
        ) $charset_collate;";
        
        dbDelta( $sql );
        
        // API logs table.
        $sql = "CREATE TABLE {$this->tables['api_logs']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            service varchar(50) NOT NULL,
            endpoint varchar(255) NOT NULL,
            request longtext NULL,
            response longtext NULL,
            status varchar(20) NOT NULL,
            duration float NOT NULL DEFAULT 0,
            credits_used int(11) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY service (service),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        dbDelta( $sql );
        
        // Credits table.
        $sql = "CREATE TABLE {$this->tables['credits']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            credits_amount int(11) NOT NULL,
            credits_type varchar(20) NOT NULL DEFAULT 'regular',
            transaction_type varchar(20) NOT NULL,
            reference_id bigint(20) unsigned NULL,
            notes text NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY credits_type (credits_type),
            KEY transaction_type (transaction_type),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        dbDelta( $sql );
        
        // Sessions table.
        $sql = "CREATE TABLE {$this->tables['sessions']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            token varchar(64) NOT NULL,
            ip_address varchar(45) NOT NULL,
            user_agent varchar(255) NOT NULL,
            last_activity datetime NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY token (token),
            KEY user_id (user_id),
            KEY last_activity (last_activity)
        ) $charset_collate;";
        
        dbDelta( $sql );
        
        // Notifications table (for storing notification logs).
        $sql = "CREATE TABLE {$this->tables['notifications']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            template_id varchar(50) NOT NULL,
            data longtext NULL,
            channels longtext NULL,
            read tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY template_id (template_id),
            KEY read (read),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        dbDelta( $sql );
        
        // Platform notifications table (for in-platform notifications).
        $sql = "CREATE TABLE {$this->tables['platform_notifications']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            title varchar(255) NOT NULL,
            message text NOT NULL,
            data longtext NULL,
            read tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY read (read),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        dbDelta( $sql );
        
        // Benchmarks table.
        $sql = "CREATE TABLE {$this->tables['benchmarks']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            industry varchar(100) NOT NULL,
            benchmark_type varchar(50) NOT NULL,
            data_source varchar(50) NOT NULL,
            metric_name varchar(100) NOT NULL,
            metric_value float NOT NULL,
            comparison_value float NULL,
            period varchar(20) NOT NULL,
            period_start date NOT NULL,
            period_end date NOT NULL,
            location varchar(100) NULL,
            device varchar(50) NULL DEFAULT 'desktop',
            sample_size int(11) NULL,
            notes text NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY industry (industry),
            KEY benchmark_type (benchmark_type),
            KEY period (period),
            KEY metric_name (metric_name),
            KEY period_start (period_start),
            KEY period_end (period_end),
            KEY location (location),
            KEY device (device)
        ) $charset_collate;";
        
        dbDelta( $sql );
    }

    /**
     * Run database migrations.
     *
     * @param string $current_version Current database version.
     * @return void
     */
    public function run_migrations( $current_version ) {
        global $wpdb;
        
        // VERSION 1.0.0
        // No migrations needed for initial version.
        
        // For future versions, add migration code here.
        // Version 1.1.0 - Add task dependencies and notifications
        if ( version_compare( $current_version, '1.1.0', '<' ) ) {
            $this->add_task_dependency_columns();
            $this->add_notification_tables();
        }
    }

    /**
     * Get table name.
     *
     * @param string $table Table key.
     * @return string|null Table name or null if not found.
     */
    public function get_table( $table ) {
        return isset( $this->tables[ $table ] ) ? $this->tables[ $table ] : null;
    }

    /**
     * Get all table names.
     *
     * @return array Table names.
     */
    public function get_tables() {
        return $this->tables;
    }

    /**
     * Check if the database needs to be updated.
     *
     * @return bool True if database needs update, false otherwise.
     */
    public function needs_update() {
        $current_version = get_option( 'ryvr_db_version', '0.0.0' );
        return version_compare( $current_version, RYVR_VERSION, '<' );
    }

    /**
     * Update the database to the latest version.
     *
     * @return void
     */
    public function update() {
        $current_version = get_option( 'ryvr_db_version', '0.0.0' );
        
        // Run migrations based on current version
        if ( version_compare( $current_version, '1.1.0', '<' ) ) {
            $this->add_task_dependency_columns();
        }
        
        // Update the database version
        update_option( 'ryvr_db_version', RYVR_VERSION );
    }
    
    /**
     * Add task dependency and priority columns to the tasks table.
     *
     * @return void
     */
    private function add_task_dependency_columns() {
        global $wpdb;
        
        $table_name = $this->get_table('tasks');
        
        // Check if columns already exist
        $columns = $wpdb->get_results("SHOW COLUMNS FROM {$table_name} LIKE 'priority'");
        if (empty($columns)) {
            $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN `priority` INT NOT NULL DEFAULT 50 AFTER `credits_cost`");
        }
        
        $columns = $wpdb->get_results("SHOW COLUMNS FROM {$table_name} LIKE 'dependencies'");
        if (empty($columns)) {
            $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN `dependencies` LONGTEXT NULL AFTER `priority`");
        }
    }

    /**
     * Add notification tables to the database.
     *
     * @return void
     */
    private function add_notification_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Include the dbDelta function.
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        
        // Notifications table (for storing notification logs).
        $sql = "CREATE TABLE {$this->tables['notifications']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            template_id varchar(50) NOT NULL,
            data longtext NULL,
            channels longtext NULL,
            read tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY template_id (template_id),
            KEY read (read),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        dbDelta( $sql );
        
        // Platform notifications table (for in-platform notifications).
        $sql = "CREATE TABLE {$this->tables['platform_notifications']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            title varchar(255) NOT NULL,
            message text NOT NULL,
            data longtext NULL,
            read tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY read (read),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        dbDelta( $sql );
    }
} 