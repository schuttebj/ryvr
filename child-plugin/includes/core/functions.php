<?php
/**
 * Core functions for the Ryvr Client.
 *
 * @package    Ryvr_Client
 * @subpackage Ryvr_Client/Core
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Log a message to the Ryvr client log file.
 *
 * @param string $message   Message to log.
 * @param string $log_level Log level. Accepts 'debug', 'info', 'warning', 'error'.
 * @return void
 */
function ryvr_client_log( $message, $log_level = 'info' ) {
    // Make sure the logs directory exists.
    $log_dir = RYVR_CLIENT_PLUGIN_DIR . 'logs';
    if ( ! file_exists( $log_dir ) ) {
        wp_mkdir_p( $log_dir );
        // Add .htaccess to protect logs.
        file_put_contents( $log_dir . '/.htaccess', 'Deny from all' );
    }

    // Create log file name.
    $log_file = $log_dir . '/ryvr-client-' . date( 'Y-m-d' ) . '.log';

    // Format message.
    $log_message = '[' . date( 'Y-m-d H:i:s' ) . '] [' . strtoupper( $log_level ) . '] ' . $message . PHP_EOL;

    // Append to log file.
    file_put_contents( $log_file, $log_message, FILE_APPEND );
}

/**
 * Get the Ryvr client option.
 *
 * @param string $option  Option name.
 * @param mixed  $default Default value.
 * @return mixed
 */
function ryvr_client_get_option( $option, $default = false ) {
    return get_option( 'ryvr_client_' . $option, $default );
}

/**
 * Update the Ryvr client option.
 *
 * @param string $option Option name.
 * @param mixed  $value  Option value.
 * @return bool
 */
function ryvr_client_update_option( $option, $value ) {
    return update_option( 'ryvr_client_' . $option, $value );
}

/**
 * Delete the Ryvr client option.
 *
 * @param string $option Option name.
 * @return bool
 */
function ryvr_client_delete_option( $option ) {
    return delete_option( 'ryvr_client_' . $option );
}

/**
 * Check if the client is connected to the parent platform.
 *
 * @return bool
 */
function ryvr_client_is_connected() {
    return ryvr_client()->is_connected();
}

/**
 * Get the parent platform URL.
 *
 * @return string
 */
function ryvr_client_get_parent_url() {
    return ryvr_client_get_option( 'parent_url', '' );
}

/**
 * Get the client API key.
 *
 * @return string
 */
function ryvr_client_get_api_key() {
    return ryvr_client_get_option( 'api_key', '' );
}

/**
 * Get the client authentication token.
 *
 * @return string
 */
function ryvr_client_get_auth_token() {
    return ryvr_client()->get_auth_token();
}

/**
 * Send a request to the parent platform.
 *
 * @param string $endpoint API endpoint.
 * @param array  $args     Request arguments.
 * @return mixed|WP_Error
 */
function ryvr_client_send_request( $endpoint, $args = [] ) {
    $api_client = ryvr_client()->get_component( 'api_client' );
    if ( ! $api_client ) {
        return new WP_Error( 'api_client_missing', __( 'API client component not found.', 'ryvr-client' ) );
    }

    return $api_client->send_request( $endpoint, $args );
}

/**
 * Format a date for display.
 *
 * @param string $date   Date string.
 * @param string $format Date format.
 * @return string
 */
function ryvr_client_format_date( $date, $format = 'F j, Y g:i a' ) {
    $timestamp = strtotime( $date );
    if ( ! $timestamp ) {
        return '';
    }

    return date_i18n( $format, $timestamp );
}

/**
 * Sanitize a string for use in a URL.
 *
 * @param string $string String to sanitize.
 * @return string
 */
function ryvr_client_sanitize_url_string( $string ) {
    return sanitize_title( $string );
}

/**
 * Get site information for identification with the parent platform.
 *
 * @return array
 */
function ryvr_client_get_site_info() {
    return [
        'site_url'     => get_site_url(),
        'site_name'    => get_bloginfo( 'name' ),
        'admin_email'  => get_bloginfo( 'admin_email' ),
        'wp_version'   => get_bloginfo( 'version' ),
        'php_version'  => phpversion(),
        'plugin_version' => RYVR_CLIENT_VERSION,
        'locale'       => get_locale(),
        'timezone'     => wp_timezone_string(),
    ];
}

/**
 * Check if a task is currently running.
 *
 * @return bool
 */
function ryvr_client_is_task_running() {
    return (bool) get_transient( 'ryvr_client_task_running' );
} 