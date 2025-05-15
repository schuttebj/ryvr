<?php
/**
 * The Admin class.
 *
 * Handles admin-related functionality.
 *
 * @package    Ryvr_Client
 * @subpackage Ryvr_Client/Admin
 */

namespace Ryvr_Client\Admin;

/**
 * The Admin class.
 *
 * Handles admin-related functionality including settings page and admin interface.
 *
 * @package    Ryvr_Client
 * @subpackage Ryvr_Client/Admin
 */
class Admin {

    /**
     * Initialize the class.
     *
     * @return void
     */
    public function init() {
        // Register hooks.
        add_action( 'admin_menu', [ $this, 'register_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
        add_action( 'admin_notices', [ $this, 'display_connection_status' ] );
        add_filter( 'plugin_action_links_' . RYVR_CLIENT_PLUGIN_BASENAME, [ $this, 'add_action_links' ] );
    }

    /**
     * Register admin menu items.
     *
     * @return void
     */
    public function register_admin_menu() {
        add_menu_page(
            __( 'Ryvr Client', 'ryvr-client' ),
            __( 'Ryvr Client', 'ryvr-client' ),
            'manage_options',
            'ryvr-client',
            [ $this, 'render_settings_page' ],
            'dashicons-admin-site',
            30
        );

        add_submenu_page(
            'ryvr-client',
            __( 'Settings', 'ryvr-client' ),
            __( 'Settings', 'ryvr-client' ),
            'manage_options',
            'ryvr-client',
            [ $this, 'render_settings_page' ]
        );

        add_submenu_page(
            'ryvr-client',
            __( 'Connection Status', 'ryvr-client' ),
            __( 'Connection Status', 'ryvr-client' ),
            'manage_options',
            'ryvr-client-status',
            [ $this, 'render_status_page' ]
        );
    }

    /**
     * Register plugin settings.
     *
     * @return void
     */
    public function register_settings() {
        register_setting( 'ryvr_client_settings', 'ryvr_client_parent_url' );
        register_setting( 'ryvr_client_settings', 'ryvr_client_api_key' );

        add_settings_section(
            'ryvr_client_connection_section',
            __( 'Connection Settings', 'ryvr-client' ),
            [ $this, 'render_connection_section' ],
            'ryvr_client_settings'
        );

        add_settings_field(
            'ryvr_client_parent_url',
            __( 'Parent Platform URL', 'ryvr-client' ),
            [ $this, 'render_parent_url_field' ],
            'ryvr_client_settings',
            'ryvr_client_connection_section'
        );

        add_settings_field(
            'ryvr_client_api_key',
            __( 'API Key', 'ryvr-client' ),
            [ $this, 'render_api_key_field' ],
            'ryvr_client_settings',
            'ryvr_client_connection_section'
        );
    }

    /**
     * Render the connection section description.
     *
     * @return void
     */
    public function render_connection_section() {
        echo '<p>';
        esc_html_e( 'Configure the connection to the parent Ryvr AI Platform.', 'ryvr-client' );
        echo '</p>';
    }

    /**
     * Render the parent URL field.
     *
     * @return void
     */
    public function render_parent_url_field() {
        $parent_url = ryvr_client_get_parent_url();
        ?>
        <input
            type="url"
            name="ryvr_client_parent_url"
            id="ryvr_client_parent_url"
            value="<?php echo esc_url( $parent_url ); ?>"
            class="regular-text"
            placeholder="https://example.com"
        />
        <p class="description">
            <?php esc_html_e( 'Enter the URL of the parent Ryvr AI Platform.', 'ryvr-client' ); ?>
        </p>
        <?php
    }

    /**
     * Render the API key field.
     *
     * @return void
     */
    public function render_api_key_field() {
        $api_key = ryvr_client_get_api_key();
        ?>
        <input
            type="password"
            name="ryvr_client_api_key"
            id="ryvr_client_api_key"
            value="<?php echo esc_attr( $api_key ); ?>"
            class="regular-text"
        />
        <p class="description">
            <?php esc_html_e( 'Enter the API key provided by the parent platform.', 'ryvr-client' ); ?>
        </p>
        <?php
    }

    /**
     * Enqueue admin assets.
     *
     * @param string $hook Current admin page hook.
     * @return void
     */
    public function enqueue_admin_assets( $hook ) {
        // Only load on our settings pages.
        if ( 'toplevel_page_ryvr-client' !== $hook && 'ryvr-client_page_ryvr-client-status' !== $hook ) {
            return;
        }

        wp_enqueue_style(
            'ryvr-client-admin',
            RYVR_CLIENT_ASSETS_URL . 'css/admin.css',
            [],
            RYVR_CLIENT_VERSION
        );

        wp_enqueue_script(
            'ryvr-client-admin',
            RYVR_CLIENT_ASSETS_URL . 'js/admin.js',
            [ 'jquery' ],
            RYVR_CLIENT_VERSION,
            true
        );

        wp_localize_script(
            'ryvr-client-admin',
            'ryvrClientAdmin',
            [
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'ryvr_client_admin_nonce' ),
                'strings' => [
                    'testConnectionSuccess' => __( 'Connection successful!', 'ryvr-client' ),
                    'testConnectionError'   => __( 'Connection failed: ', 'ryvr-client' ),
                    'confirmDisconnect'     => __( 'Are you sure you want to disconnect from the parent platform?', 'ryvr-client' ),
                ],
            ]
        );
    }

    /**
     * Display connection status notice.
     *
     * @return void
     */
    public function display_connection_status() {
        $screen = get_current_screen();
        if ( ! $screen || false === strpos( $screen->id, 'ryvr-client' ) ) {
            return;
        }

        if ( ryvr_client_is_connected() ) {
            ?>
            <div class="notice notice-success">
                <p><?php esc_html_e( 'Connected to Ryvr AI Platform.', 'ryvr-client' ); ?></p>
            </div>
            <?php
        } else {
            $parent_url = ryvr_client_get_parent_url();
            $api_key = ryvr_client_get_api_key();

            if ( empty( $parent_url ) || empty( $api_key ) ) {
                ?>
                <div class="notice notice-warning">
                    <p>
                        <?php esc_html_e( 'Please configure the connection to the Ryvr AI Platform.', 'ryvr-client' ); ?>
                    </p>
                </div>
                <?php
            } else {
                // We have settings but not connected.
                $connector = ryvr_client()->get_component( 'parent_connector' );
                $error = $connector ? $connector->get_last_error() : null;
                ?>
                <div class="notice notice-error">
                    <p>
                        <?php
                        esc_html_e( 'Not connected to Ryvr AI Platform. ', 'ryvr-client' );
                        if ( $error ) {
                            echo esc_html( $error->get_error_message() );
                        }
                        ?>
                    </p>
                </div>
                <?php
            }
        }
    }

    /**
     * Add plugin action links.
     *
     * @param array $links Plugin action links.
     * @return array
     */
    public function add_action_links( $links ) {
        $settings_link = '<a href="' . admin_url( 'admin.php?page=ryvr-client' ) . '">' . __( 'Settings', 'ryvr-client' ) . '</a>';
        array_unshift( $links, $settings_link );
        return $links;
    }

    /**
     * Render the settings page.
     *
     * @return void
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Ryvr Client Settings', 'ryvr-client' ); ?></h1>

            <form method="post" action="options.php">
                <?php
                settings_fields( 'ryvr_client_settings' );
                do_settings_sections( 'ryvr_client_settings' );
                submit_button( __( 'Save Settings', 'ryvr-client' ) );
                ?>
            </form>

            <div class="ryvr-client-connection-actions">
                <h2><?php esc_html_e( 'Connection Actions', 'ryvr-client' ); ?></h2>
                
                <p>
                    <button type="button" id="ryvr-client-test-connection" class="button button-secondary">
                        <?php esc_html_e( 'Test Connection', 'ryvr-client' ); ?>
                    </button>
                    
                    <button type="button" id="ryvr-client-reconnect" class="button button-secondary">
                        <?php esc_html_e( 'Reconnect', 'ryvr-client' ); ?>
                    </button>
                    
                    <button type="button" id="ryvr-client-disconnect" class="button button-secondary">
                        <?php esc_html_e( 'Disconnect', 'ryvr-client' ); ?>
                    </button>
                </p>
                
                <div id="ryvr-client-connection-result" class="hidden"></div>
            </div>
        </div>
        <?php
    }

    /**
     * Render the status page.
     *
     * @return void
     */
    public function render_status_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Ryvr Client Status', 'ryvr-client' ); ?></h1>

            <div class="ryvr-client-status-card">
                <h2><?php esc_html_e( 'Connection Status', 'ryvr-client' ); ?></h2>
                
                <table class="widefat striped">
                    <tbody>
                        <tr>
                            <th><?php esc_html_e( 'Connected', 'ryvr-client' ); ?></th>
                            <td>
                                <?php if ( ryvr_client_is_connected() ) : ?>
                                    <span class="ryvr-client-status-connected"><?php esc_html_e( 'Yes', 'ryvr-client' ); ?></span>
                                <?php else : ?>
                                    <span class="ryvr-client-status-disconnected"><?php esc_html_e( 'No', 'ryvr-client' ); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Parent Platform URL', 'ryvr-client' ); ?></th>
                            <td><?php echo esc_url( ryvr_client_get_parent_url() ); ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'API Key', 'ryvr-client' ); ?></th>
                            <td><?php echo ryvr_client_get_api_key() ? '••••••••' : esc_html__( 'Not set', 'ryvr-client' ); ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Token Status', 'ryvr-client' ); ?></th>
                            <td>
                                <?php
                                $token_expiration = (int) get_option( 'ryvr_client_token_expiration', 0 );
                                if ( $token_expiration > 0 ) {
                                    if ( $token_expiration > time() ) {
                                        echo sprintf(
                                            /* translators: %s: human-readable time difference */
                                            esc_html__( 'Valid (expires in %s)', 'ryvr-client' ),
                                            human_time_diff( time(), $token_expiration )
                                        );
                                    } else {
                                        esc_html_e( 'Expired', 'ryvr-client' );
                                    }
                                } else {
                                    esc_html_e( 'Not available', 'ryvr-client' );
                                }
                                ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="ryvr-client-status-card">
                <h2><?php esc_html_e( 'Site Information', 'ryvr-client' ); ?></h2>
                
                <?php $site_info = ryvr_client_get_site_info(); ?>
                
                <table class="widefat striped">
                    <tbody>
                        <tr>
                            <th><?php esc_html_e( 'Site URL', 'ryvr-client' ); ?></th>
                            <td><?php echo esc_url( $site_info['site_url'] ); ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Site Name', 'ryvr-client' ); ?></th>
                            <td><?php echo esc_html( $site_info['site_name'] ); ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'WordPress Version', 'ryvr-client' ); ?></th>
                            <td><?php echo esc_html( $site_info['wp_version'] ); ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'PHP Version', 'ryvr-client' ); ?></th>
                            <td><?php echo esc_html( $site_info['php_version'] ); ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Plugin Version', 'ryvr-client' ); ?></th>
                            <td><?php echo esc_html( $site_info['plugin_version'] ); ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Timezone', 'ryvr-client' ); ?></th>
                            <td><?php echo esc_html( $site_info['timezone'] ); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }
} 