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
class DataForSEO_Service extends API_Service {
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
    public function __construct($db_manager, $cache, $user_id = null, $api_key = null, $api_secret = null) {
        $this->service_name = 'dataforseo';
        parent::__construct($db_manager, $cache, $user_id, $api_key, $api_secret);
        
        // Create the actual service instance
        $this->service = new Services\DataForSEO_Service();
        
        // Initialize the service
        $this->service->init();
        
        // Set credentials if provided
        if ($api_key && $api_secret) {
            // Store them in the original format for API_Service compatibility
            $this->api_key = $api_key;
            $this->api_secret = $api_secret;
            
            // Set in the actual service
            // Note: DataForSEO uses username/password terminology
            update_option('ryvr_dataforseo_api_login', $api_key);
            update_option('ryvr_dataforseo_api_password', $api_secret);
        }
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
        
        // Fall back to parent methods
        if (method_exists(parent::class, $name)) {
            return call_user_func_array([parent::class, $name], $arguments);
        }
        
        trigger_error('Call to undefined method ' . __CLASS__ . '::' . $name . '()', E_USER_ERROR);
        return null;
    }
    
    /**
     * Calculate credits used for an API request.
     *
     * @param string $endpoint API endpoint.
     * @param array  $params   Request parameters.
     * @param array  $response Response data.
     * @return float Credits used.
     */
    protected function calculate_credits_used($endpoint, $params, $response) {
        // Default credit cost
        $credits = 1;
        
        // If the response contains a cost field, use that
        if (isset($response['cost'])) {
            $credits = (float)$response['cost'];
        }
        
        return $credits;
    }
    
    /**
     * Execute the actual API request.
     *
     * @param string $endpoint API endpoint.
     * @param array  $params   Request parameters.
     * @param string $method   HTTP method.
     * @return array Response data.
     */
    protected function execute_request($endpoint, $params, $method) {
        // Forward the request to the actual service
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
        
        // Forward to the service if it has a suitable method
        if (method_exists($this->service, 'make_request')) {
            return $this->service->make_request($endpoint, $params, $method);
        }
        
        // Fall back to a standard WordPress HTTP request
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
     * Generate a sandbox response for testing.
     *
     * @param string $endpoint API endpoint.
     * @param array  $params   Request parameters.
     * @param string $method   HTTP method.
     * @return array Mock response data.
     */
    protected function generate_sandbox_response($endpoint, $params, $method) {
        // Forward to the service if it has a suitable method
        if (method_exists($this->service, 'test_connection')) {
            return $this->service->test_connection();
        }
        
        // Default sandbox response
        return [
            'status_code' => 20000,
            'status_message' => 'Sandbox mode: OK',
            'time' => date('Y-m-d\TH:i:s\Z'),
            'cost' => 0,
            'tasks_count' => 1,
            'tasks_error' => 0,
            'tasks' => [
                [
                    'id' => 'sandbox-' . uniqid(),
                    'status_code' => 20000,
                    'status_message' => 'Sandbox data',
                    'time' => date('Y-m-d\TH:i:s\Z'),
                    'result' => [
                        'sandbox' => true,
                        'endpoint' => $endpoint,
                        'method' => $method,
                    ],
                ],
            ],
            'sandbox' => true,
        ];
    }
} 