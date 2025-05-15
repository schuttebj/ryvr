<?php
/**
 * Abstract API Service class.
 *
 * Provides a base for all API service implementations.
 *
 * @package    Ryvr
 * @subpackage Ryvr/API
 */

namespace Ryvr\API;

use Ryvr\Database\Database_Manager;

/**
 * Abstract API Service class.
 *
 * This class provides common functionality for all API services including
 * caching, rate limiting, error handling, and usage tracking.
 *
 * @package    Ryvr
 * @subpackage Ryvr/API
 */
abstract class API_Service {

    /**
     * The service identifier.
     *
     * @var string
     */
    protected $service_name;

    /**
     * Database manager instance.
     *
     * @var Database_Manager
     */
    protected $db_manager;

    /**
     * API Cache instance.
     *
     * @var API_Cache
     */
    protected $cache;

    /**
     * API key.
     *
     * @var string
     */
    protected $api_key;

    /**
     * API secret or additional auth data.
     *
     * @var string
     */
    protected $api_secret;

    /**
     * Base URL for API requests.
     *
     * @var string
     */
    protected $api_base_url;

    /**
     * Current user ID.
     *
     * @var int
     */
    protected $user_id;

    /**
     * Sandbox mode flag.
     *
     * @var bool
     */
    protected $sandbox_mode = false;

    /**
     * Whether to use caching.
     *
     * @var bool
     */
    protected $use_caching = true;

    /**
     * Constructor.
     *
     * @param Database_Manager $db_manager Database manager instance.
     * @param API_Cache        $cache      API Cache instance.
     * @param int              $user_id    User ID.
     * @param string           $api_key    API key.
     * @param string           $api_secret API secret.
     */
    public function __construct( Database_Manager $db_manager, API_Cache $cache, $user_id = null, $api_key = null, $api_secret = null ) {
        $this->db_manager = $db_manager;
        $this->cache = $cache;
        $this->user_id = $user_id ?: get_current_user_id();
        
        // Initialize with provided credentials or get from database
        if ( $api_key ) {
            $this->api_key = $api_key;
            $this->api_secret = $api_secret;
        } else {
            $this->load_credentials();
        }
        
        // Check if sandbox mode is enabled
        $this->sandbox_mode = (bool) get_user_meta( $this->user_id, 'ryvr_api_sandbox_mode', true );
    }

    /**
     * Load API credentials from database.
     *
     * @return bool True if credentials loaded successfully, false otherwise.
     */
    protected function load_credentials() {
        global $wpdb;
        
        $table = $this->db_manager->get_table( 'api_keys' );
        
        $query = $wpdb->prepare(
            "SELECT api_key, api_secret FROM $table 
             WHERE user_id = %d AND service = %s AND is_active = 1 
             ORDER BY id DESC LIMIT 1",
            $this->user_id,
            $this->service_name
        );
        
        $result = $wpdb->get_row( $query );
        
        if ( $result ) {
            $this->api_key = $result->api_key;
            $this->api_secret = $result->api_secret;
            return true;
        }
        
        return false;
    }

    /**
     * Set sandbox mode.
     *
     * @param bool $enabled Whether to enable sandbox mode.
     * @return void
     */
    public function set_sandbox_mode( $enabled ) {
        $this->sandbox_mode = (bool) $enabled;
        update_user_meta( $this->user_id, 'ryvr_api_sandbox_mode', $this->sandbox_mode );
    }

    /**
     * Check if sandbox mode is enabled.
     *
     * @return bool
     */
    public function is_sandbox_mode() {
        return $this->sandbox_mode;
    }

    /**
     * Enable/disable caching.
     *
     * @param bool $enabled Whether to enable caching.
     * @return void
     */
    public function set_caching( $enabled ) {
        $this->use_caching = (bool) $enabled;
    }

    /**
     * Get cached response or make API request.
     *
     * @param string $endpoint API endpoint.
     * @param array  $params   Request parameters.
     * @param string $method   HTTP method.
     * @param int    $ttl      Cache TTL in seconds.
     * @return array Response data.
     */
    protected function request_with_cache( $endpoint, $params = array(), $method = 'GET', $ttl = null ) {
        // Check if we should use cache
        if ( $this->use_caching && !$this->sandbox_mode && $method === 'GET' ) {
            // Try to get from cache first
            $cached = $this->cache->get( $this->service_name, $endpoint, $params );
            
            if ( $cached !== false ) {
                return $cached;
            }
        }
        
        // If not in cache or caching disabled, make the actual request
        $response = $this->make_request( $endpoint, $params, $method );
        
        // If successful and caching is enabled, cache the response
        if ( $this->use_caching && !$this->sandbox_mode && $method === 'GET' && !isset( $response['error'] ) ) {
            $this->cache->set( $this->service_name, $endpoint, $params, $response, $ttl );
        }
        
        return $response;
    }

    /**
     * Make API request.
     *
     * @param string $endpoint API endpoint.
     * @param array  $params   Request parameters.
     * @param string $method   HTTP method.
     * @return array Response data.
     */
    protected function make_request( $endpoint, $params = array(), $method = 'GET' ) {
        // Start timer for tracking
        $start_time = microtime( true );
        
        // Default response
        $response = array(
            'success' => false,
            'data' => null,
            'error' => null
        );
        
        try {
            // Check if in sandbox mode
            if ( $this->sandbox_mode ) {
                return $this->generate_sandbox_response( $endpoint, $params, $method );
            }
            
            // Make actual API request
            $result = $this->execute_request( $endpoint, $params, $method );
            
            // Process successful response
            $response['success'] = true;
            $response['data'] = $result;
        } catch ( \Exception $e ) {
            // Handle error
            $response['error'] = array(
                'code' => $e->getCode(),
                'message' => $e->getMessage()
            );
        }
        
        // Calculate request duration
        $duration = microtime( true ) - $start_time;
        
        // Log API call
        $this->log_api_call( $endpoint, $params, $response, $duration );
        
        return $response;
    }

    /**
     * Log API call to database.
     *
     * @param string $endpoint API endpoint.
     * @param array  $params   Request parameters.
     * @param array  $response Response data.
     * @param float  $duration Request duration in seconds.
     * @return void
     */
    protected function log_api_call( $endpoint, $params, $response, $duration ) {
        global $wpdb;
        
        // Don't log in sandbox mode
        if ( $this->sandbox_mode ) {
            return;
        }
        
        $table = $this->db_manager->get_table( 'api_logs' );
        
        // Determine status from response
        $status = isset( $response['error'] ) ? 'error' : 'success';
        
        // Calculate credits used (implemented by child classes)
        $credits_used = $this->calculate_credits_used( $endpoint, $params, $response );
        
        // Prepare data for logging
        $log_data = array(
            'user_id' => $this->user_id,
            'service' => $this->service_name,
            'endpoint' => $endpoint,
            'request' => maybe_serialize( $this->sanitize_for_log( $params ) ),
            'response' => maybe_serialize( $this->sanitize_for_log( $response ) ),
            'status' => $status,
            'duration' => round( $duration, 4 ),
            'credits_used' => $credits_used,
            'created_at' => current_time( 'mysql' )
        );
        
        // Insert log entry
        $wpdb->insert( $table, $log_data );
        
        // Track credits usage if needed
        if ( $credits_used > 0 ) {
            $this->track_credits_usage( $credits_used, $wpdb->insert_id );
        }
    }

    /**
     * Track credits usage in the credits table.
     *
     * @param int $credits_used  Number of credits used.
     * @param int $reference_id  API log entry ID.
     * @return void
     */
    protected function track_credits_usage( $credits_used, $reference_id ) {
        global $wpdb;
        
        $table = $this->db_manager->get_table( 'credits' );
        
        $data = array(
            'user_id' => $this->user_id,
            'credits_amount' => -$credits_used, // Negative for usage
            'credits_type' => 'regular',
            'transaction_type' => 'api_usage',
            'reference_id' => $reference_id,
            'notes' => sprintf( 'API usage: %s - %s', $this->service_name, current_time( 'mysql' ) ),
            'created_at' => current_time( 'mysql' )
        );
        
        $wpdb->insert( $table, $data );
    }

    /**
     * Sanitize sensitive data for logging.
     *
     * @param array $data Data to sanitize.
     * @return array Sanitized data.
     */
    protected function sanitize_for_log( $data ) {
        // Make a copy to avoid modifying the original
        $sanitized = is_array( $data ) ? $data : array();
        
        // Hide API keys, passwords, etc.
        if ( is_array( $sanitized ) ) {
            foreach ( $sanitized as $key => $value ) {
                // Check for sensitive keys
                if ( in_array( $key, array( 'api_key', 'key', 'secret', 'password', 'token' ) ) ) {
                    $sanitized[$key] = '***REDACTED***';
                } 
                // Recursively sanitize nested arrays
                else if ( is_array( $value ) ) {
                    $sanitized[$key] = $this->sanitize_for_log( $value );
                }
            }
        }
        
        return $sanitized;
    }

    /**
     * Calculate credits used for an API call.
     *
     * @param string $endpoint API endpoint.
     * @param array  $params   Request parameters.
     * @param array  $response Response data.
     * @return int Credits used.
     */
    abstract protected function calculate_credits_used( $endpoint, $params, $response );

    /**
     * Execute the actual API request.
     *
     * @param string $endpoint API endpoint.
     * @param array  $params   Request parameters.
     * @param string $method   HTTP method.
     * @return mixed Response data.
     * @throws \Exception If the request fails.
     */
    abstract protected function execute_request( $endpoint, $params, $method );

    /**
     * Generate a fake response when in sandbox mode.
     *
     * @param string $endpoint API endpoint.
     * @param array  $params   Request parameters.
     * @param string $method   HTTP method.
     * @return array Mock response data.
     */
    abstract protected function generate_sandbox_response( $endpoint, $params, $method );

    /**
     * Check if user has enough credits for the operation.
     *
     * @param int $required_credits Required credits.
     * @return bool True if user has enough credits, false otherwise.
     */
    protected function check_credits( $required_credits ) {
        // Get user's current credit balance
        $balance = $this->get_credit_balance();
        
        // Check if balance is sufficient
        return $balance >= $required_credits;
    }

    /**
     * Get user's current credit balance.
     *
     * @return int Current credit balance.
     */
    protected function get_credit_balance() {
        global $wpdb;
        
        $table = $this->db_manager->get_table( 'credits' );
        
        $query = $wpdb->prepare(
            "SELECT SUM(credits_amount) FROM $table WHERE user_id = %d",
            $this->user_id
        );
        
        $balance = $wpdb->get_var( $query );
        
        return (int) $balance ?: 0;
    }

    /**
     * Clear service cache.
     *
     * @return int Number of cache entries cleared.
     */
    public function clear_cache() {
        return $this->cache->clear_service_cache( $this->service_name );
    }

    /**
     * Clear endpoint cache.
     *
     * @param string $endpoint API endpoint.
     * @return int Number of cache entries cleared.
     */
    public function clear_endpoint_cache( $endpoint ) {
        return $this->cache->clear_endpoint_cache( $this->service_name, $endpoint );
    }

    /**
     * Get recent API logs for user.
     *
     * @param int $limit Maximum number of logs to return.
     * @return array Recent API logs.
     */
    public function get_recent_logs( $limit = 10 ) {
        global $wpdb;
        
        $table = $this->db_manager->get_table( 'api_logs' );
        
        $query = $wpdb->prepare(
            "SELECT * FROM $table 
             WHERE user_id = %d AND service = %s 
             ORDER BY created_at DESC LIMIT %d",
            $this->user_id,
            $this->service_name,
            $limit
        );
        
        return $wpdb->get_results( $query );
    }

    /**
     * Get API service usage statistics.
     *
     * @param string $period Period type (day, week, month, year).
     * @return array Usage statistics.
     */
    public function get_usage_stats( $period = 'month' ) {
        global $wpdb;
        
        $table = $this->db_manager->get_table( 'api_logs' );
        
        // Determine date range based on period
        $date_range = $this->get_date_range_for_period( $period );
        
        $query = $wpdb->prepare(
            "SELECT 
                COUNT(*) as call_count,
                SUM(credits_used) as total_credits,
                AVG(duration) as avg_duration,
                COUNT(CASE WHEN status = 'error' THEN 1 END) as error_count,
                SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) / COUNT(*) * 100 as success_rate
             FROM $table 
             WHERE user_id = %d 
               AND service = %s
               AND created_at BETWEEN %s AND %s",
            $this->user_id,
            $this->service_name,
            $date_range['start'],
            $date_range['end']
        );
        
        $stats = $wpdb->get_row( $query, ARRAY_A );
        
        // Add endpoint breakdown
        $query = $wpdb->prepare(
            "SELECT 
                endpoint,
                COUNT(*) as call_count,
                SUM(credits_used) as total_credits
             FROM $table 
             WHERE user_id = %d 
               AND service = %s
               AND created_at BETWEEN %s AND %s
             GROUP BY endpoint
             ORDER BY call_count DESC",
            $this->user_id,
            $this->service_name,
            $date_range['start'],
            $date_range['end']
        );
        
        $stats['endpoints'] = $wpdb->get_results( $query );
        
        return $stats;
    }

    /**
     * Get date range for a period.
     *
     * @param string $period Period type (day, week, month, year).
     * @return array Start and end dates.
     */
    private function get_date_range_for_period( $period ) {
        $end = current_time( 'mysql' );
        
        switch ( $period ) {
            case 'day':
                $start = date( 'Y-m-d 00:00:00', strtotime( 'today' ) );
                break;
            case 'week':
                $start = date( 'Y-m-d 00:00:00', strtotime( 'monday this week' ) );
                break;
            case 'month':
                $start = date( 'Y-m-01 00:00:00' );
                break;
            case 'year':
                $start = date( 'Y-01-01 00:00:00' );
                break;
            default:
                $start = date( 'Y-m-d 00:00:00', strtotime( '-30 days' ) );
        }
        
        return array(
            'start' => $start,
            'end' => $end
        );
    }
} 