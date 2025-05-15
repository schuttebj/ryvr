<?php
/**
 * Plugin Name: Ryvr AI Client
 * Plugin URI: https://ryvr.ai
 * Description: Client plugin for the Ryvr AI Platform enabling content deployment and task execution.
 * Version: 1.0.0
 * Author: Ryvr
 * Author URI: https://ryvr.ai
 * Text Domain: ryvr-client
 * Domain Path: /languages
 * Requires at least: 5.9
 * Requires PHP: 7.4
 *
 * @package Ryvr_Client
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants.
define( 'RYVR_CLIENT_VERSION', '1.0.0' );
define( 'RYVR_CLIENT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'RYVR_CLIENT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'RYVR_CLIENT_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'RYVR_CLIENT_INCLUDES_DIR', RYVR_CLIENT_PLUGIN_DIR . 'includes/' );
define( 'RYVR_CLIENT_ASSETS_DIR', RYVR_CLIENT_PLUGIN_DIR . 'assets/' );
define( 'RYVR_CLIENT_ASSETS_URL', RYVR_CLIENT_PLUGIN_URL . 'assets/' );
define( 'RYVR_CLIENT_TEMPLATES_DIR', RYVR_CLIENT_PLUGIN_DIR . 'templates/' );

/**
 * Class Ryvr_Client
 *
 * Main plugin class that bootstraps the client plugin.
 */
final class Ryvr_Client {

    /**
     * Plugin instance.
     *
     * @var Ryvr_Client
     */
    private static $instance = null;

    /**
     * Plugin components.
     *
     * @var array
     */
    private $components = [];

    /**
     * Parent connection status.
     *
     * @var bool
     */
    private $is_connected = false;

    /**
     * Authentication token.
     *
     * @var string
     */
    private $auth_token = '';

    /**
     * Get the singleton instance.
     *
     * @return Ryvr_Client
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

        // Connect to parent platform.
        $this->connect_to_parent();

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
        require_once RYVR_CLIENT_INCLUDES_DIR . 'core/functions.php';
        require_once RYVR_CLIENT_INCLUDES_DIR . 'core/class-loader.php';
        require_once RYVR_CLIENT_INCLUDES_DIR . 'core/class-i18n.php';
        require_once RYVR_CLIENT_INCLUDES_DIR . 'core/class-activator.php';
        require_once RYVR_CLIENT_INCLUDES_DIR . 'core/class-deactivator.php';

        // Load API files.
        require_once RYVR_CLIENT_INCLUDES_DIR . 'api/class-api-client.php';
        require_once RYVR_CLIENT_INCLUDES_DIR . 'api/class-parent-connector.php';

        // Load Admin files.
        require_once RYVR_CLIENT_INCLUDES_DIR . 'admin/class-admin.php';
    }

    /**
     * Initialize components.
     *
     * @return void
     */
    private function init_components() {
        // Create components.
        $this->components['loader'] = new Ryvr_Client\Core\Loader();
        $this->components['i18n'] = new Ryvr_Client\Core\I18n();
        $this->components['api_client'] = new Ryvr_Client\API\API_Client();
        $this->components['parent_connector'] = new Ryvr_Client\API\Parent_Connector();
        $this->components['admin'] = new Ryvr_Client\Admin\Admin();

        // Initialize components.
        $this->components['i18n']->init();
        $this->components['api_client']->init();
        $this->components['parent_connector']->init();
        $this->components['admin']->init();
    }

    /**
     * Connect to parent platform.
     *
     * @return void
     */
    private function connect_to_parent() {
        // Check if connection settings exist.
        $parent_url = get_option( 'ryvr_client_parent_url', '' );
        $api_key = get_option( 'ryvr_client_api_key', '' );

        if ( empty( $parent_url ) || empty( $api_key ) ) {
            // Not configured yet, connection will be attempted after settings are saved.
            $this->is_connected = false;
            return;
        }

        // Attempt to authenticate with parent.
        $connector = $this->components['parent_connector'];
        $auth_result = $connector->authenticate( $parent_url, $api_key );

        if ( is_wp_error( $auth_result ) ) {
            // Authentication failed.
            $this->is_connected = false;
            add_action( 'admin_notices', [ $this, 'display_connection_error' ] );
            return;
        }

        // Authentication successful.
        $this->is_connected = true;
        $this->auth_token = $auth_result;
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
        load_plugin_textdomain( 'ryvr-client', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    }

    /**
     * Plugin activation.
     *
     * @return void
     */
    public function activate() {
        // Run activator.
        Ryvr_Client\Core\Activator::activate();
    }

    /**
     * Plugin deactivation.
     *
     * @return void
     */
    public function deactivate() {
        // Run deactivator.
        Ryvr_Client\Core\Deactivator::deactivate();
    }

    /**
     * Display connection error notice.
     *
     * @return void
     */
    public function display_connection_error() {
        $connector = $this->components['parent_connector'];
        $error = $connector->get_last_error();

        ?>
        <div class="notice notice-error is-dismissible">
            <p>
                <?php
                echo wp_kses(
                    sprintf(
                        /* translators: %1$s: Error message, %2$s: Settings page URL */
                        __( 'Error connecting to Ryvr AI Platform: %1$s. <a href="%2$s">Check your connection settings</a>.', 'ryvr-client' ),
                        $error ? $error->get_error_message() : __( 'Unknown error', 'ryvr-client' ),
                        admin_url( 'admin.php?page=ryvr-client-settings' )
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
     * Check if connected to parent platform.
     *
     * @return bool Connection status.
     */
    public function is_connected() {
        return $this->is_connected;
    }

    /**
     * Get the authentication token.
     *
     * @return string Authentication token.
     */
    public function get_auth_token() {
        return $this->auth_token;
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
    private function __wakeup() {
        // Prevent unserializing.
    }
}

/**
 * Returns the main instance of the Ryvr Client.
 *
 * @return Ryvr_Client
 */
function ryvr_client() {
    return Ryvr_Client::get_instance();
}

// Initialize the plugin.
ryvr_client(); 