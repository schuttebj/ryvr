<?php
/**
 * The OpenAI Service class.
 *
 * Handles integration with the OpenAI API.
 *
 * @package    Ryvr
 * @subpackage Ryvr/API/Services
 */

namespace Ryvr\API\Services;

/**
 * The OpenAI Service class.
 *
 * This class provides methods for interacting with the OpenAI API.
 *
 * @package    Ryvr
 * @subpackage Ryvr/API/Services
 */
class OpenAI_Service {

    /**
     * API endpoint.
     *
     * @var string
     */
    private $api_endpoint = 'https://api.openai.com/v1';

    /**
     * API key.
     *
     * @var string
     */
    private $api_key = '';

    /**
     * Current client ID.
     *
     * @var int
     */
    private $client_id = 0;

    /**
     * Initialize the service.
     *
     * @return void
     */
    public function init() {
        // Load settings.
        $this->load_settings();
    }

    /**
     * Load API settings.
     *
     * @return void
     */
    private function load_settings() {
        // Get global API key.
        $this->api_key = get_option('ryvr_openai_api_key', '');
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
            $client_api_key = get_post_meta($client_id, 'ryvr_openai_api_key', true);
            
            // Override global settings with client settings if available
            if (!empty($client_api_key)) {
                $this->api_key = $client_api_key;
            }
        }
    }

    /**
     * Make an API request to OpenAI.
     *
     * @param string $endpoint API endpoint.
     * @param array  $params Request parameters.
     * @param string $method HTTP method (GET, POST, etc.).
     * @return mixed Response data or WP_Error on failure.
     */
    private function make_request($endpoint, $params = [], $method = 'POST') {
        // Start timing the request
        $start_time = microtime(true);
        
        // Check if API key is set.
        if (empty($this->api_key)) {
            return new \WP_Error('missing_api_key', __('OpenAI API key is not set.', 'ryvr-ai'));
        }
        
        // Build request URL.
        $url = trailingslashit($this->api_endpoint) . ltrim($endpoint, '/');
        
        // Prepare request arguments.
        $args = [
            'method'  => $method,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type'  => 'application/json',
            ],
            'timeout' => 60,
        ];
        
        // Add body for POST, PUT, PATCH.
        if (in_array($method, ['POST', 'PUT', 'PATCH'], true) && !empty($params)) {
            $args['body'] = wp_json_encode($params);
        }
        
        // Make the request.
        $response = wp_remote_request($url, $args);
        
        // Calculate request duration
        $duration = microtime(true) - $start_time;
        
        // Handle errors.
        if (is_wp_error($response)) {
            $this->log_request($endpoint, $params, $response, 'error', $duration);
            return $response;
        }
        
        // Get response code.
        $response_code = wp_remote_retrieve_response_code($response);
        
        // Parse response body.
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        // Handle API errors.
        if ($response_code >= 400) {
            $error_message = isset($data['error']['message']) ? $data['error']['message'] : __('Unknown error', 'ryvr-ai');
            $error = new \WP_Error('openai_api_error', $error_message, $data);
            
            $this->log_request($endpoint, $params, $error, 'error', $duration);
            return $error;
        }
        
        // Calculate credits used
        $credits_used = $this->calculate_credits_used($endpoint, $params, $data);
        
        // Log the successful request
        $this->log_request($endpoint, $params, $data, 'success', $duration, $credits_used);
        
        return $data;
    }

    /**
     * Test API connection.
     *
     * @return true|WP_Error True on success, WP_Error on failure.
     */
    public function test_connection() {
        // Make a simple request to verify the API key.
        $response = $this->make_request('models', [], 'GET');
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return true;
    }

    /**
     * Check if the API is configured.
     *
     * @return bool Whether API key is configured.
     */
    public function is_configured() {
        return !empty($this->api_key);
    }

    /**
     * Calculate credits used for an API request.
     *
     * @param string $endpoint API endpoint.
     * @param array  $params Request parameters.
     * @param array  $response Response data.
     * @return int Credits used.
     */
    private function calculate_credits_used($endpoint, $params, $response) {
        // Default to 1 credit.
        $credits = 1;
        
        // Different endpoints have different credit costs.
        // This is a simplified calculation - in a real implementation,
        // you would account for token counts and model-specific costs.
        if (strpos($endpoint, 'chat/completions') !== false) {
            // Chat completions cost varies based on input and output tokens.
            $credits = 2;
            
            // Check if usage information is available.
            if (isset($response['usage'])) {
                $prompt_tokens = isset($response['usage']['prompt_tokens']) ? $response['usage']['prompt_tokens'] : 0;
                $completion_tokens = isset($response['usage']['completion_tokens']) ? $response['usage']['completion_tokens'] : 0;
                
                // Calculate credits based on token usage.
                // Here we use a simplified formula - adjust to your actual pricing model.
                $prompt_credits = ceil($prompt_tokens / 1000);
                $completion_credits = ceil($completion_tokens / 1000) * 2; // Output tokens are often more expensive.
                
                $credits = max(1, $prompt_credits + $completion_credits);
            }
        } elseif (strpos($endpoint, 'embeddings') !== false) {
            // Embedding requests.
            $credits = 1;
        } elseif (strpos($endpoint, 'images/generations') !== false) {
            // Image generation costs more.
            $credits = 5;
        }
        
        return $credits;
    }
    
    /**
     * Log API request.
     *
     * @param string $endpoint API endpoint.
     * @param array  $params Request parameters.
     * @param mixed  $response Response data or error.
     * @param string $status Request status.
     * @param float  $duration Request duration in seconds.
     * @param int    $credits Credits used.
     * @return void
     */
    private function log_request($endpoint, $params, $response, $status, $duration = 0, $credits = 0) {
        // Check if logging is enabled
        if (get_option('ryvr_log_api_calls') !== 'on') {
            return;
        }
        
        // Prepare log data
        $log = [
            'time'      => current_time('mysql'),
            'service'   => 'openai',
            'endpoint'  => $endpoint,
            'data'      => $params,
            'response'  => is_wp_error($response) ? $response->get_error_message() : $response,
            'status'    => $status,
            'duration'  => round($duration, 3),
            'credits'   => $credits,
            'client_id' => $this->client_id,
        ];
        
        // Log to database
        global $wpdb;
        $table_name = $wpdb->prefix . 'ryvr_api_logs';
        
        $wpdb->insert(
            $table_name,
            [
                'service'    => 'openai',
                'endpoint'   => $endpoint,
                'request'    => wp_json_encode($params),
                'response'   => wp_json_encode($response),
                'status'     => $status,
                'duration'   => $duration,
                'credits'    => $credits,
                'created_at' => current_time('mysql'),
                'user_id'    => get_current_user_id(),
                'client_id'  => $this->client_id,
            ]
        );
    }

    /**
     * Generate text using a completion model.
     *
     * @param string $prompt The prompt to generate from.
     * @param array  $options Generation options.
     * @return mixed Response data or WP_Error on failure.
     */
    public function generate_completion($prompt, $options = []) {
        // Default options.
        $defaults = [
            'model'       => 'gpt-3.5-turbo-instruct',
            'max_tokens'  => 1024,
            'temperature' => 0.7,
            'top_p'       => 1,
            'frequency_penalty' => 0,
            'presence_penalty'  => 0,
            'stop'        => null,
        ];
        
        // Merge with provided options.
        $options = wp_parse_args($options, $defaults);
        
        // Add prompt to options.
        $options['prompt'] = $prompt;
        
        // Make the API request.
        return $this->make_request('completions', $options);
    }

    /**
     * Generate chat-based completions.
     *
     * @param array $messages Array of message objects.
     * @param array $options Generation options.
     * @return mixed Response data or WP_Error on failure.
     */
    public function generate_chat_completion($messages, $options = []) {
        // Default options.
        $defaults = [
            'model'       => 'gpt-3.5-turbo',
            'max_tokens'  => 1024,
            'temperature' => 0.7,
            'top_p'       => 1,
            'frequency_penalty' => 0,
            'presence_penalty'  => 0,
            'stop'        => null,
        ];
        
        // Merge with provided options.
        $options = wp_parse_args($options, $defaults);
        
        // Add messages to options.
        $options['messages'] = $messages;
        
        // Make the API request.
        return $this->make_request('chat/completions', $options);
    }

    /**
     * Generate embeddings for text.
     *
     * @param string $text The text to generate embeddings for.
     * @param string $model The embedding model to use.
     * @return mixed Response data or WP_Error on failure.
     */
    public function generate_embeddings($text, $model = 'text-embedding-ada-002') {
        $params = [
            'model' => $model,
            'input' => $text,
        ];
        
        return $this->make_request('embeddings', $params);
    }

    /**
     * Generate an image from a prompt.
     *
     * @param string $prompt The prompt to generate an image from.
     * @param array  $options Generation options.
     * @return mixed Response data or WP_Error on failure.
     */
    public function generate_image($prompt, $options = []) {
        // Default options.
        $defaults = [
            'n'    => 1,
            'size' => '1024x1024',
            'response_format' => 'url',
        ];
        
        // Merge with provided options.
        $options = wp_parse_args($options, $defaults);
        
        // Add prompt to options.
        $options['prompt'] = $prompt;
        
        // Make the API request.
        return $this->make_request('images/generations', $options);
    }
} 