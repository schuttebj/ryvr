<?php
/**
 * The Abstract API Service class.
 *
 * Defines the interface for API service implementations.
 *
 * @package    Ryvr
 * @subpackage Ryvr/API
 */

namespace Ryvr\API;

/**
 * The Abstract API Service class.
 *
 * This class defines the interface that all API service
 * implementations must follow.
 *
 * @package    Ryvr
 * @subpackage Ryvr/API
 */
abstract class API_Service {

    /**
     * API service name.
     *
     * @var string
     */
    protected $service_name = '';
    
    /**
     * API service description.
     *
     * @var string
     */
    protected $service_description = '';
    
    /**
     * API key option name.
     *
     * @var string
     */
    protected $api_key_option = '';
    
    /**
     * API secret option name.
     *
     * @var string
     */
    protected $api_secret_option = '';
    
    /**
     * Whether this service requires an API secret.
     *
     * @var bool
     */
    protected $requires_api_secret = false;
    
    /**
     * Default API endpoint.
     *
     * @var string
     */
    protected $api_endpoint = '';
    
    /**
     * API version.
     *
     * @var string
     */
    protected $api_version = '';
    
    /**
     * Debug mode.
     *
     * @var bool
     */
    protected $debug_mode = false;
    
    /**
     * Whether to log API calls.
     *
     * @var bool
     */
    protected $log_api_calls = false;

    /**
     * Initialize the service.
     *
     * This method should be overridden by child classes to perform
     * any initialization tasks.
     *
     * @return void
     */
    public function init() {
        $this->debug_mode = get_option( 'ryvr_debug_mode', 'off' ) === 'on';
        $this->log_api_calls = get_option( 'ryvr_log_api_calls', 'off' ) === 'on';
    }

    /**
     * Make an API request.
     *
     * @param string $endpoint API endpoint.
     * @param array  $params Request parameters.
     * @param string $method HTTP method (GET, POST, etc.).
     * @return mixed Response data or WP_Error on failure.
     */
    abstract public function request( $endpoint, $params = [], $method = 'GET' );

    /**
     * Test the API connection.
     *
     * @param string $api_key API key.
     * @param string $api_secret API secret (optional).
     * @return bool|WP_Error True on success, WP_Error on failure.
     */
    abstract public function test_connection( $api_key, $api_secret = '' );

    /**
     * Save API settings.
     *
     * @param string $api_key API key.
     * @param string $api_secret API secret (optional).
     * @return bool|WP_Error True on success, WP_Error on failure.
     */
    public function save_settings( $api_key, $api_secret = '' ) {
        // Check if the credentials are valid.
        $test_result = $this->test_connection( $api_key, $api_secret );
        
        if ( is_wp_error( $test_result ) ) {
            return $test_result;
        }
        
        // Save API key.
        update_option( $this->api_key_option, $api_key );
        
        // Save API secret if required.
        if ( $this->requires_api_secret ) {
            update_option( $this->api_secret_option, $api_secret );
        }
        
        return true;
    }

    /**
     * Get the API key.
     *
     * @return string API key.
     */
    public function get_api_key() {
        return get_option( $this->api_key_option, '' );
    }

    /**
     * Get the API secret.
     *
     * @return string API secret.
     */
    public function get_api_secret() {
        return get_option( $this->api_secret_option, '' );
    }

    /**
     * Get the service name.
     *
     * @return string Service name.
     */
    public function get_service_name() {
        return $this->service_name;
    }

    /**
     * Get the service description.
     *
     * @return string Service description.
     */
    public function get_service_description() {
        return $this->service_description;
    }

    /**
     * Check if API credentials are set.
     *
     * @return bool True if credentials are set, false otherwise.
     */
    public function is_configured() {
        $api_key = $this->get_api_key();
        
        if ( empty( $api_key ) ) {
            return false;
        }
        
        if ( $this->requires_api_secret && empty( $this->get_api_secret() ) ) {
            return false;
        }
        
        return true;
    }

    /**
     * Log an API call.
     *
     * @param string $endpoint API endpoint.
     * @param array  $request Request data.
     * @param mixed  $response Response data.
     * @param string $status Response status.
     * @param float  $duration Request duration in seconds.
     * @param int    $credits_used Credits used for the request.
     * @return int|false Log ID on success, false on failure.
     */
    protected function log_request( $endpoint, $request = [], $response = [], $status = 'success', $duration = 0, $credits_used = 0 ) {
        global $wpdb;
        
        // Skip logging if logging is disabled.
        if ( ! $this->log_api_calls && ! $this->debug_mode ) {
            return false;
        }
        
        // Get the current user ID.
        $user_id = get_current_user_id();
        
        // Prepare request and response data for storage.
        $request_data = $this->prepare_log_data( $request );
        $response_data = $this->prepare_log_data( $response );
        
        // Insert log record.
        $table_name = $wpdb->prefix . 'ryvr_api_logs';
        
        $result = $wpdb->insert(
            $table_name,
            [
                'user_id'      => $user_id,
                'service'      => $this->service_name,
                'endpoint'     => $endpoint,
                'request'      => $request_data,
                'response'     => $response_data,
                'status'       => $status,
                'duration'     => $duration,
                'credits_used' => $credits_used,
                'created_at'   => current_time( 'mysql', true ),
            ],
            [
                '%d',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%f',
                '%d',
                '%s',
            ]
        );
        
        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Prepare data for logging.
     *
     * @param mixed $data Data to prepare.
     * @return string JSON-encoded data.
     */
    private function prepare_log_data( $data ) {
        // If data is an array or object, convert it to JSON.
        if ( is_array( $data ) || is_object( $data ) ) {
            // Remove sensitive data.
            $data = $this->remove_sensitive_data( $data );
            
            // Encode to JSON.
            $json = wp_json_encode( $data );
            
            // Truncate if too large.
            if ( strlen( $json ) > 65535 ) {
                $json = substr( $json, 0, 65532 ) . '...';
            }
            
            return $json;
        }
        
        // Return as string.
        return (string) $data;
    }

    /**
     * Remove sensitive data from log data.
     *
     * @param mixed $data Data to process.
     * @return mixed Processed data.
     */
    private function remove_sensitive_data( $data ) {
        if ( ! is_array( $data ) ) {
            return $data;
        }
        
        $sensitive_keys = [
            'api_key',
            'apikey',
            'api-key',
            'key',
            'secret',
            'password',
            'token',
            'auth',
            'authorization',
        ];
        
        foreach ( $data as $key => $value ) {
            // Check if key is sensitive.
            $key_lower = strtolower( $key );
            
            if ( in_array( $key_lower, $sensitive_keys, true ) || strpos( $key_lower, 'secret' ) !== false || strpos( $key_lower, 'pass' ) !== false ) {
                $data[ $key ] = '[REDACTED]';
                continue;
            }
            
            // Recurse into arrays and objects.
            if ( is_array( $value ) || is_object( $value ) ) {
                $data[ $key ] = $this->remove_sensitive_data( $value );
            }
        }
        
        return $data;
    }
} 