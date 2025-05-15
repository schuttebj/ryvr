<?php
/**
 * Debug page for Ryvr AI Platform
 *
 * @package    Ryvr
 * @subpackage Ryvr/Admin
 */

namespace Ryvr\Admin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * The debug page class.
 *
 * Provides an admin interface for viewing and managing logs.
 */
class Debug_Page {

    /**
     * Hook the page into WordPress.
     */
    public function init() {
        add_action( 'admin_menu', [ $this, 'add_submenu_page' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
        add_action( 'wp_ajax_ryvr_clear_log', [ $this, 'ajax_clear_log' ] );
        add_action( 'wp_ajax_ryvr_change_log_level', [ $this, 'ajax_change_log_level' ] );
    }

    /**
     * Add submenu page to the Ryvr menu.
     */
    public function add_submenu_page() {
        add_submenu_page(
            'ryvr-ai',
            __( 'Debug Logs', 'ryvr-ai' ),
            __( 'Debug Logs', 'ryvr-ai' ),
            'manage_options',
            'ryvr-ai-debug',
            [ $this, 'render_page' ]
        );
    }

    /**
     * Enqueue scripts and styles for the debug page.
     *
     * @param string $hook Current admin page.
     */
    public function enqueue_scripts( $hook ) {
        if ( 'ryvr-ai_page_ryvr-ai-debug' !== $hook ) {
            return;
        }

        wp_enqueue_style(
            'ryvr-debug',
            RYVR_ASSETS_URL . 'css/debug.css',
            [],
            RYVR_VERSION
        );

        wp_enqueue_script(
            'ryvr-debug',
            RYVR_ASSETS_URL . 'js/debug.js',
            [ 'jquery' ],
            RYVR_VERSION,
            true
        );

        wp_localize_script(
            'ryvr-debug',
            'ryvrDebug',
            [
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'ryvr_debug_nonce' ),
            ]
        );
    }

    /**
     * Render the debug page.
     */
    public function render_page() {
        // Check if user has permission
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'ryvr-ai' ) );
        }

        // Get date from query string or use today
        $date = isset( $_GET['date'] ) ? sanitize_text_field( $_GET['date'] ) : 'today';
        
        // Get log files
        $log_files = ryvr_debug()->get_log_files();
        
        // Get current log level
        $current_level = get_option( 'ryvr_debug_level', 'info' );
        
        // Get log content
        $log_content = ryvr_debug()->get_log_content( $date );
        
        // Log levels for dropdown
        $log_levels = [
            'emergency' => __( 'Emergency', 'ryvr-ai' ),
            'alert'     => __( 'Alert', 'ryvr-ai' ),
            'critical'  => __( 'Critical', 'ryvr-ai' ),
            'error'     => __( 'Error', 'ryvr-ai' ),
            'warning'   => __( 'Warning', 'ryvr-ai' ),
            'notice'    => __( 'Notice', 'ryvr-ai' ),
            'info'      => __( 'Info', 'ryvr-ai' ),
            'debug'     => __( 'Debug', 'ryvr-ai' ),
        ];
        
        ?>
        <div class="wrap ryvr-debug-page">
            <h1><?php esc_html_e( 'Ryvr Debug Logs', 'ryvr-ai' ); ?></h1>
            
            <div class="ryvr-debug-controls">
                <div class="ryvr-debug-dates">
                    <label for="ryvr-log-date"><?php esc_html_e( 'Select Date:', 'ryvr-ai' ); ?></label>
                    <select id="ryvr-log-date" name="date">
                        <option value="today" <?php selected( $date, 'today' ); ?>><?php esc_html_e( 'Today', 'ryvr-ai' ); ?></option>
                        <?php foreach ( $log_files as $log_date ) : ?>
                            <option value="<?php echo esc_attr( $log_date ); ?>" <?php selected( $date, $log_date ); ?>>
                                <?php echo esc_html( $log_date ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="ryvr-debug-level">
                    <label for="ryvr-log-level"><?php esc_html_e( 'Log Level:', 'ryvr-ai' ); ?></label>
                    <select id="ryvr-log-level" name="level">
                        <?php foreach ( $log_levels as $level => $label ) : ?>
                            <option value="<?php echo esc_attr( $level ); ?>" <?php selected( $current_level, $level ); ?>>
                                <?php echo esc_html( $label ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="ryvr-debug-actions">
                    <button id="ryvr-refresh-log" class="button"><?php esc_html_e( 'Refresh', 'ryvr-ai' ); ?></button>
                    <button id="ryvr-clear-log" class="button"><?php esc_html_e( 'Clear Log', 'ryvr-ai' ); ?></button>
                    <button id="ryvr-download-log" class="button"><?php esc_html_e( 'Download Log', 'ryvr-ai' ); ?></button>
                </div>
            </div>
            
            <div class="ryvr-debug-content">
                <?php if ( empty( $log_content ) ) : ?>
                    <div class="ryvr-debug-empty">
                        <?php esc_html_e( 'No log entries found for this date.', 'ryvr-ai' ); ?>
                    </div>
                <?php else : ?>
                    <pre id="ryvr-log-viewer"><?php echo esc_html( $log_content ); ?></pre>
                <?php endif; ?>
            </div>
            
            <div class="ryvr-debug-footer">
                <h2><?php esc_html_e( 'Debug Functions', 'ryvr-ai' ); ?></h2>
                <p><?php esc_html_e( 'Use these functions in your code to log debug information:', 'ryvr-ai' ); ?></p>
                
                <div class="ryvr-debug-function">
                    <code>ryvr_log_debug( $message, $level = 'info', $component = 'core', $context = [] )</code>
                    <p><?php esc_html_e( 'Logs a message with the specified level and component.', 'ryvr-ai' ); ?></p>
                </div>
                
                <div class="ryvr-debug-function">
                    <code>ryvr_dump( $var, $component = 'debug' )</code>
                    <p><?php esc_html_e( 'Dumps a variable and logs it with debug level.', 'ryvr-ai' ); ?></p>
                </div>
                
                <div class="ryvr-debug-function">
                    <code>ryvr_log_api( $service, $endpoint, $request, $response )</code>
                    <p><?php esc_html_e( 'Logs an API request and response.', 'ryvr-ai' ); ?></p>
                </div>
                
                <div class="ryvr-debug-function">
                    <code>ryvr_debug()->info( $message, $component = 'core', $context = [] )</code>
                    <p><?php esc_html_e( 'Logs an info message (also available: emergency, alert, critical, error, warning, notice, debug).', 'ryvr-ai' ); ?></p>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX handler for clearing the log.
     */
    public function ajax_clear_log() {
        // Check nonce
        check_ajax_referer( 'ryvr_debug_nonce', 'nonce' );
        
        // Check permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'You do not have permission to clear logs.', 'ryvr-ai' ) ] );
        }
        
        // Get date from request
        $date = isset( $_POST['date'] ) ? sanitize_text_field( $_POST['date'] ) : 'today';
        
        // Clear log
        $cleared = ryvr_debug()->clear_log( $date );
        
        if ( $cleared ) {
            wp_send_json_success( [ 'message' => __( 'Log cleared successfully.', 'ryvr-ai' ) ] );
        } else {
            wp_send_json_error( [ 'message' => __( 'Failed to clear log.', 'ryvr-ai' ) ] );
        }
    }

    /**
     * AJAX handler for changing the log level.
     */
    public function ajax_change_log_level() {
        // Check nonce
        check_ajax_referer( 'ryvr_debug_nonce', 'nonce' );
        
        // Check permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'You do not have permission to change log level.', 'ryvr-ai' ) ] );
        }
        
        // Get level from request
        $level = isset( $_POST['level'] ) ? sanitize_text_field( $_POST['level'] ) : 'info';
        
        // Valid log levels
        $valid_levels = [ 'emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug' ];
        
        // Validate level
        if ( ! in_array( $level, $valid_levels, true ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid log level.', 'ryvr-ai' ) ] );
        }
        
        // Update option
        update_option( 'ryvr_debug_level', $level );
        
        wp_send_json_success( [ 'message' => __( 'Log level updated successfully.', 'ryvr-ai' ) ] );
    }
} 