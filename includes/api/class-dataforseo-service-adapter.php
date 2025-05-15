<?php
/**
 * DataForSEO Service Adapter.
 *
 * Provides backward compatibility for the DataForSEO service.
 *
 * @package    Ryvr
 * @subpackage Ryvr/API
 */

namespace Ryvr\API;

use Ryvr\Database\Database_Manager;

// Make sure the service class is loaded first
require_once RYVR_INCLUDES_DIR . 'api/services/class-dataforseo-service.php';

/**
 * The DataForSEO_Service adapter class.
 *
 * This class acts as a compatibility layer to bridge the gap between
 * the two DataForSEO service implementations.
 *
 * @package    Ryvr
 * @subpackage Ryvr/API
 */
class DataForSEO_Service {
    /**
     * Service name
     * 
     * @var string
     */
    protected $service_name = 'dataforseo';
    
    /**
     * API key (username for DataForSEO)
     * 
     * @var string
     */
    protected $api_key;
    
    /**
     * API secret (password for DataForSEO)
     * 
     * @var string
     */
    protected $api_secret;
    
    /**
     * API base URL
     * 
     * @var string
     */
    protected $api_base_url = 'https://api.dataforseo.com/v3';
    
    /**
     * Whether sandbox mode is enabled
     * 
     * @var bool
     */
    protected $sandbox_mode = false;
    
    /**
     * The actual service instance
     * 
     * @var Services\DataForSEO_Service
     */
    private $service;

    /**
     * Constructor.
     *
     * @param Database_Manager $db_manager Database manager instance.
     * @param API_Cache        $cache      API Cache instance.
     * @param int              $user_id    User ID.
     * @param string           $api_key    API key (username for DataForSEO).
     * @param string           $api_secret API secret (password for DataForSEO).
     */
    public function __construct($db_manager = null, $cache = null, $user_id = null, $api_key = null, $api_secret = null) {
        // Set credentials if provided
        if ($api_key && $api_secret) {
            $this->api_key = $api_key;
            $this->api_secret = $api_secret;
        } else {
            // Try to get from options
            $this->api_key = get_option('ryvr_dataforseo_api_login', '');
            $this->api_secret = get_option('ryvr_dataforseo_api_password', '');
        }
        
        // Set sandbox mode based on option
        $this->sandbox_mode = (bool) get_option('ryvr_api_sandbox_mode', false);
        
        // Create the actual service instance
        $this->service = new Services\DataForSEO_Service();
        
        // Initialize the service
        $this->service->init();
        
        // Set credentials in options for consistency
        if ($api_key && $api_secret) {
            update_option('ryvr_dataforseo_api_login', $api_key);
            update_option('ryvr_dataforseo_api_password', $api_secret);
        }
    }
    
    /**
     * Initialize the service.
     *
     * @return void
     */
    public function init() {
        // Already initialized in constructor
    }
    
    /**
     * Check if the service is configured.
     *
     * @return bool Whether the service is configured.
     */
    public function is_configured() {
        return !empty($this->api_key) && !empty($this->api_secret);
    }
    
    /**
     * Magic method to forward calls to the actual service
     *
     * @param string $name      Method name.
     * @param array  $arguments Method arguments.
     * @return mixed
     */
    public function __call($name, $arguments) {
        if (method_exists($this->service, $name)) {
            return call_user_func_array([$this->service, $name], $arguments);
        }
        
        trigger_error('Call to undefined method ' . __CLASS__ . '::' . $name . '()', E_USER_ERROR);
        return null;
    }
    
    /**
     * Execute an API request.
     *
     * @param string $endpoint Endpoint to call.
     * @param array  $params   Request parameters.
     * @param string $method   HTTP method (GET, POST, etc.).
     * @return array Response data.
     */
    public function request($endpoint, $params = [], $method = 'POST') {
        // Forward to the actual service if it has a suitable method
        if (method_exists($this->service, 'make_request')) {
            return $this->service->make_request($endpoint, $params, $method);
        }
        
        // Fall back to a standard WordPress HTTP request
        $url = $this->api_base_url . '/' . ltrim($endpoint, '/');
        
        $args = [
            'method' => $method,
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($this->api_key . ':' . $this->api_secret),
                'Content-Type' => 'application/json',
            ],
            'timeout' => 30,
        ];
        
        // Handle different HTTP methods
        if ($method === 'GET') {
            $url = add_query_arg($params, $url);
        } else {
            $args['body'] = wp_json_encode($params);
        }
        
        // Make the request
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            throw new \Exception($response->get_error_message(), $response->get_error_code() ?: 500);
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        $data = json_decode($response_body, true);
        
        if ($response_code >= 400 || (isset($data['status_code']) && $data['status_code'] >= 400)) {
            $error_message = isset($data['status_message']) ? $data['status_message'] : 'Unknown API error';
            throw new \Exception($error_message, $response_code ?: 500);
        }
        
        return $data;
    }
    
    /**
     * Test the API connection.
     *
     * @return bool|array
     */
    public function test_connection() {
        // Forward to the service if it has a suitable method
        if (method_exists($this->service, 'test_connection')) {
            return $this->service->test_connection();
        }
        
        // Default test connection logic
        try {
            $response = $this->request('app_info');
            return $response;
        } catch (\Exception $e) {
            return false;
        }
    }
} 