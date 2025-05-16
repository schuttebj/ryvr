<?php
/**
 * The DataForSEO Service class.
 *
 * Handles integration with the DataForSEO API.
 *
 * @package    Ryvr
 * @subpackage Ryvr/API/Services
 */

namespace Ryvr\API\Services;

/**
 * The DataForSEO Service class.
 *
 * This class handles all interactions with the DataForSEO API.
 *
 * @package    Ryvr
 * @subpackage Ryvr/API/Services
 */
class DataForSEO_Service {

    /**
     * API Base URL for live environment.
     *
     * @var string
     */
    private $live_api_url = 'https://api.dataforseo.com/';

    /**
     * API Base URL for sandbox environment.
     *
     * @var string
     */
    private $sandbox_api_url = 'https://sandbox.dataforseo.com/';

    /**
     * API username.
     *
     * @var string
     */
    private $username = '';

    /**
     * API password.
     *
     * @var string
     */
    private $password = '';

    /**
     * Current client ID.
     *
     * @var int
     */
    private $client_id = 0;

    /**
     * Whether to use sandbox mode.
     *
     * @var bool
     */
    private $sandbox_mode = false;

    /**
     * Initialize the service.
     *
     * @return void
     */
    public function init() {
        // Load settings
        $this->load_settings();
    }

    /**
     * Load API settings.
     *
     * @return void
     */
    private function load_settings() {
        // Get global settings
        $this->username = get_option('ryvr_dataforseo_api_login', '');
        $this->password = get_option('ryvr_dataforseo_api_password', '');
        $this->sandbox_mode = get_option('ryvr_dataforseo_sandbox_mode', 'on') === 'on';
    }

    /**
     * Set client ID for client-specific API keys.
     *
     * @param int $client_id Client ID.
     * @return void
     */
    public function set_client_id($client_id) {
        $this->client_id = $client_id;
        
        // If client ID is set, try to load client-specific settings
        if ($client_id > 0) {
            $client_username = get_post_meta($client_id, 'ryvr_dataforseo_username', true);
            $client_password = get_post_meta($client_id, 'ryvr_dataforseo_password', true);
            
            // Override global settings with client settings if available
            if (!empty($client_username)) {
                $this->username = $client_username;
            }
            
            if (!empty($client_password)) {
                $this->password = $client_password;
            }
        }
    }

    /**
     * Get the API base URL based on environment.
     *
     * @return string API base URL.
     */
    private function get_api_url() {
        return $this->sandbox_mode ? $this->sandbox_api_url : $this->live_api_url;
    }

    /**
     * Make an API request.
     *
     * @param string $endpoint API endpoint.
     * @param array  $data Request data.
     * @param string $method HTTP method.
     * @return array|WP_Error Response data or error.
     */
    private function make_request($endpoint, $data = [], $method = 'POST') {
        // Check if credentials are set
        if (empty($this->username) || empty($this->password)) {
            return new \WP_Error('missing_credentials', __('DataForSEO API credentials are not set.', 'ryvr-ai'));
        }

        // Build request URL
        $url = $this->get_api_url() . ltrim($endpoint, '/');

        // Set up request args
        $args = [
            'method'  => $method,
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Basic ' . base64_encode($this->username . ':' . $this->password),
            ],
            'timeout' => 60,
        ];

        // Add body for POST requests
        if ($method === 'POST' && !empty($data)) {
            $args['body'] = wp_json_encode($data);
        }

        // Make the request
        $response = wp_remote_request($url, $args);

        // Check for errors
        if (is_wp_error($response)) {
            return $response;
        }

        // Get response code
        $response_code = wp_remote_retrieve_response_code($response);

        // Check if response is successful
        if ($response_code < 200 || $response_code >= 300) {
            return new \WP_Error(
                'api_error',
                sprintf(__('DataForSEO API error: %s', 'ryvr-ai'), $response_code),
                $response
            );
        }

        // Parse response body
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (empty($data)) {
            return new \WP_Error('invalid_response', __('Invalid response from DataForSEO API.', 'ryvr-ai'));
        }

        // Check for API errors
        if (isset($data['status_code']) && $data['status_code'] !== 20000) {
            return new \WP_Error(
                'api_error',
                isset($data['status_message']) ? $data['status_message'] : __('Unknown API error.', 'ryvr-ai'),
                $data
            );
        }

        return $data;
    }

    /**
     * Test API connection.
     *
     * @return true|WP_Error True on success, WP_Error on failure.
     */
    public function test_connection() {
        // Try a simple API call to test connection
        $result = $this->make_request('v3/app_info');

        if (is_wp_error($result)) {
            return $result;
        }

        return true;
    }

    /**
     * Get keyword suggestions.
     *
     * @param string $keyword Seed keyword.
     * @param array  $options Additional options.
     * @return array|WP_Error Response data or error.
     */
    public function keyword_suggestions($keyword, $options = []) {
        $defaults = [
            'location_code' => 2840, // US by default
            'language_code' => 'en',
            'limit' => 100,
        ];

        $options = wp_parse_args($options, $defaults);

        $data = [
            'keyword' => $keyword,
            'location_code' => $options['location_code'],
            'language_code' => $options['language_code'],
            'limit' => $options['limit'],
        ];

        return $this->make_request('v3/keywords_data/google/keywords_for_keywords/live', [$data]);
    }

    /**
     * Get keyword search volume.
     *
     * @param array $keywords List of keywords.
     * @param array $options Additional options.
     * @return array|WP_Error Response data or error.
     */
    public function keyword_search_volume($keywords, $options = []) {
        $defaults = [
            'location_code' => 2840, // US by default
            'language_code' => 'en',
        ];

        $options = wp_parse_args($options, $defaults);

        // Prepare data
        $data = [];
        foreach ($keywords as $keyword) {
            $data[] = [
                'keyword' => $keyword,
                'location_code' => $options['location_code'],
                'language_code' => $options['language_code'],
            ];
        }

        return $this->make_request('v3/keywords_data/google/search_volume/live', $data);
    }

    /**
     * Get domain keywords.
     *
     * @param string $domain Domain name.
     * @param array  $options Additional options.
     * @return array|WP_Error Response data or error.
     */
    public function domain_keywords($domain, $options = []) {
        $defaults = [
            'location_code' => 2840, // US by default
            'language_code' => 'en',
            'limit' => 100,
        ];

        $options = wp_parse_args($options, $defaults);

        $data = [
            'target' => $domain,
            'location_code' => $options['location_code'],
            'language_code' => $options['language_code'],
            'limit' => $options['limit'],
        ];

        return $this->make_request('v3/keywords_data/google/organic/live', [$data]);
    }

    /**
     * Perform site audit.
     *
     * @param string $domain Domain to audit.
     * @param array  $options Additional options.
     * @return array|WP_Error Response data or error.
     */
    public function site_audit($domain, $options = []) {
        $defaults = [
            'limit' => 100,
            'max_crawl_depth' => 2,
        ];

        $options = wp_parse_args($options, $defaults);

        $data = [
            'target' => $domain,
            'max_crawl_pages' => $options['limit'],
            'crawl_depth' => $options['max_crawl_depth'],
        ];

        return $this->make_request('v3/on_page/task_post', [$data]);
    }

    /**
     * Check task status.
     *
     * @param string $task_id Task ID.
     * @return array|WP_Error Response data or error.
     */
    public function check_task_status($task_id) {
        return $this->make_request('v3/on_page/tasks', [], 'GET');
    }
    
    /**
     * Get domain competitors.
     *
     * @param string $domain Domain name.
     * @param array  $options Additional options.
     * @return array|WP_Error Response data or error.
     */
    public function domain_competitors($domain, $options = []) {
        $defaults = [
            'location_code' => 2840, // US by default
            'language_code' => 'en',
            'limit' => 10,
        ];

        $options = wp_parse_args($options, $defaults);

        $data = [
            'target' => $domain,
            'location_code' => $options['location_code'],
            'language_code' => $options['language_code'],
            'limit' => $options['limit'],
        ];

        return $this->make_request('v3/keywords_data/google/competitors/live', [$data]);
    }

    /**
     * Get domain backlinks.
     *
     * @param string $domain Domain name.
     * @param array  $options Additional options.
     * @return array|WP_Error Response data or error.
     */
    public function domain_backlinks($domain, $options = []) {
        $defaults = [
            'limit' => 100,
            'mode' => 'domain',
        ];

        $options = wp_parse_args($options, $defaults);

        $data = [
            'target' => $domain,
            'limit' => $options['limit'],
            'mode' => $options['mode'],
        ];

        return $this->make_request('v3/backlinks/overview/live', [$data]);
    }

    /**
     * Get domain authority metrics.
     * 
     * @param string $domain Domain name.
     * @return array|WP_Error Response data or error.
     */
    public function domain_authority($domain) {
        $data = [
            'target' => $domain,
        ];

        return $this->make_request('v3/backlinks/domain_rank/live', [$data]);
    }

    /**
     * Get ads keywords for a domain.
     *
     * @param string $domain Domain name.
     * @param array  $options Additional options.
     * @return array|WP_Error Response data or error.
     */
    public function ads_keywords($domain, $options = []) {
        $defaults = [
            'location_code' => 2840, // US by default
            'language_code' => 'en',
            'limit' => 100,
        ];

        $options = wp_parse_args($options, $defaults);

        $data = [
            'target' => $domain,
            'location_code' => $options['location_code'],
            'language_code' => $options['language_code'],
            'limit' => $options['limit'],
        ];

        return $this->make_request('v3/keywords_data/google/ads/live', [$data]);
    }

    /**
     * Check if the API is configured.
     *
     * @return bool Whether API credentials are configured.
     */
    public function is_configured() {
        return !empty($this->username) && !empty($this->password);
    }
} 