<?php
/**
 * DataForSEO API Service.
 *
 * Handles communication with the DataForSEO API.
 *
 * @package    Ryvr
 * @subpackage Ryvr/API
 */

namespace Ryvr\API;

use Ryvr\Database\Database_Manager;

/**
 * DataForSEO API Service Class.
 *
 * Implementation of the API_Service for DataForSEO API integration.
 * 
 * @link https://docs.dataforseo.com/v3/
 *
 * @package    Ryvr
 * @subpackage Ryvr/API
 */
class DataForSEO_Service extends API_Service {

    /**
     * The API base URL.
     *
     * @var string
     */
    protected $api_base_url = 'https://api.dataforseo.com/v3';

    /**
     * API endpoints with their credit costs.
     *
     * @var array
     */
    protected $endpoints = array(
        // SERP API
        'serp' => array(
            'google' => array(
                'organic' => array(
                    'live' => 2,
                    'task_post' => 1,
                    'task_get' => 1,
                ),
                'local_pack' => array(
                    'live' => 3,
                    'task_post' => 1,
                    'task_get' => 2,
                ),
            ),
        ),
        // Keywords Data API
        'keywords_data' => array(
            'google' => array(
                'search_volume' => array(
                    'live' => 0.5,
                    'task_post' => 0.2,
                    'task_get' => 0.3,
                ),
                'keywords_for_site' => array(
                    'live' => 5,
                    'task_post' => 2,
                    'task_get' => 3,
                ),
                'keywords_for_keywords' => array(
                    'live' => 5,
                    'task_post' => 2,
                    'task_get' => 3,
                ),
            ),
        ),
        // On-Page API
        'on_page' => array(
            'task_post' => 5,
            'task_get' => 10,
            'pages' => 0.1, // per page
            'lighthouse' => 5,
            'content_parsing' => 2,
            'instant_pages' => 0.5, // per page
        ),
        // Backlinks API
        'backlinks' => array(
            'summary' => 1,
            'backlinks' => 0.002, // per backlink
            'domain_pages' => 0.5,
            'anchors' => 0.5,
        ),
    );

    /**
     * Constructor.
     *
     * @param Database_Manager $db_manager Database manager instance.
     * @param API_Cache        $cache      API Cache instance.
     * @param int              $user_id    User ID.
     * @param string           $api_key    API key (username for DataForSEO).
     * @param string           $api_secret API secret (password for DataForSEO).
     */
    public function __construct( Database_Manager $db_manager, API_Cache $cache, $user_id = null, $api_key = null, $api_secret = null ) {
        $this->service_name = 'dataforseo';
        parent::__construct( $db_manager, $cache, $user_id, $api_key, $api_secret );
    }

    /**
     * Get available endpoints and their credit costs.
     *
     * @return array Endpoints with credit costs.
     */
    public function get_available_endpoints() {
        return $this->endpoints;
    }

    /**
     * Execute the actual API request.
     *
     * @param string $endpoint API endpoint.
     * @param array  $params   Request parameters.
     * @param string $method   HTTP method.
     * @param int    $timeout  Request timeout in seconds.
     * @return mixed Response data.
     * @throws \Exception If the request fails.
     */
    protected function execute_request( $endpoint, $params = array(), $method = 'GET', $timeout = 30 ) {
        // Check if we have API credentials
        if ( empty( $this->api_key ) || empty( $this->api_secret ) ) {
            throw new \Exception( 'DataForSEO API credentials not provided.', 401 );
        }
        
        $url = $this->api_base_url . '/' . $endpoint;
        
        $args = array(
            'method' => $method,
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode( $this->api_key . ':' . $this->api_secret ),
                'Content-Type' => 'application/json',
            ),
            'timeout' => $timeout,
        );
        
        // Handle different HTTP methods
        if ( $method === 'GET' ) {
            $url = add_query_arg( $params, $url );
        } else {
            $args['body'] = wp_json_encode( $params );
        }
        
        // Make the request
        $response = wp_remote_request( $url, $args );
        
        // Check for errors
        if ( is_wp_error( $response ) ) {
            throw new \Exception( $response->get_error_message(), $response->get_error_code() ?: 500 );
        }
        
        $response_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );
        
        // Parse the response
        $data = json_decode( $response_body, true );
        
        // Handle error responses
        if ( $response_code >= 400 || ( isset( $data['status_code'] ) && $data['status_code'] >= 400 ) ) {
            $error_message = isset( $data['status_message'] ) ? $data['status_message'] : 'Unknown API error';
            throw new \Exception( $error_message, $response_code ?: 500 );
        }
        
        return $data;
    }

    /**
     * Generate a fake response when in sandbox mode.
     *
     * @param string $endpoint API endpoint.
     * @param array  $params   Request parameters.
     * @param string $method   HTTP method.
     * @return array Mock response data.
     */
    protected function generate_sandbox_response( $endpoint, $params, $method ) {
        // Default sandbox response structure
        $response = array(
            'status_code' => 20000,
            'status_message' => 'Sandbox mode: OK',
            'time' => date('Y-m-d\TH:i:s\Z'),
            'cost' => 0,
            'tasks_count' => 1,
            'tasks_error' => 0,
            'tasks' => array(),
            'sandbox' => true,
        );
        
        // Generate a random task ID
        $task_id = 'sandbox-' . uniqid();
        
        // Generate different responses based on endpoint
        if ( strpos( $endpoint, 'serp/' ) === 0 ) {
            $response['tasks'][] = $this->generate_sandbox_serp_results( $endpoint, $params, $task_id );
        } elseif ( strpos( $endpoint, 'keywords_data/' ) === 0 ) {
            $response['tasks'][] = $this->generate_sandbox_keywords_data( $endpoint, $params, $task_id );
        } elseif ( strpos( $endpoint, 'on_page/' ) === 0 ) {
            $response['tasks'][] = $this->generate_sandbox_on_page_data( $endpoint, $params, $task_id );
        } elseif ( strpos( $endpoint, 'backlinks/' ) === 0 ) {
            $response['tasks'][] = $this->generate_sandbox_backlinks_data( $endpoint, $params, $task_id );
        } else {
            // Generic sandbox response for unknown endpoints
            $response['tasks'][] = array(
                'id' => $task_id,
                'status_code' => 20000,
                'status_message' => 'Sandbox mode: Task Complete',
                'time' => date('Y-m-d\TH:i:s\Z'),
                'result' => array(
                    'sandbox_message' => 'This is a generic sandbox response for ' . $endpoint,
                ),
            );
        }
        
        return $response;
    }

    /**
     * Calculate credits used for an API call.
     *
     * @param string $endpoint API endpoint.
     * @param array  $params   Request parameters.
     * @param array  $response Response data.
     * @return int Credits used.
     */
    protected function calculate_credits_used( $endpoint, $params, $response ) {
        // Don't charge for errors
        if ( isset( $response['error'] ) || ( isset( $response['data']['status_code'] ) && $response['data']['status_code'] >= 40000 ) ) {
            return 0;
        }
        
        // If API includes the cost, use it
        if ( isset( $response['data']['cost'] ) ) {
            return (float) $response['data']['cost'] * 100; // Convert to credits (1 DFS dollar = 100 credits)
        }
        
        // Otherwise, calculate based on endpoint
        return $this->estimate_credits_for_endpoint( $endpoint, $params, $response );
    }

    /**
     * Estimate credits for an endpoint based on our pricing table.
     *
     * @param string $endpoint API endpoint.
     * @param array  $params   Request parameters.
     * @param array  $response Response data.
     * @return int Estimated credits.
     */
    private function estimate_credits_for_endpoint( $endpoint, $params, $response ) {
        $credits = 0;
        
        // Parse endpoint to get the relevant sections of our pricing table
        $parts = explode( '/', $endpoint );
        
        // SERP API endpoint calculation
        if ( $parts[0] === 'serp' && isset( $parts[1] ) && isset( $parts[2] ) ) {
            $search_engine = $parts[1]; // e.g., google
            $search_type = $parts[2];   // e.g., organic
            
            // Live vs task endpoints
            $endpoint_type = 'live';
            if ( isset( $parts[3] ) ) {
                if ( $parts[3] === 'task_post' ) {
                    $endpoint_type = 'task_post';
                } elseif ( $parts[3] === 'task_get' || strpos( $parts[3], 'tasks' ) === 0 ) {
                    $endpoint_type = 'task_get';
                }
            }
            
            // Get the credit cost from our pricing table
            if ( isset( $this->endpoints['serp'][$search_engine][$search_type][$endpoint_type] ) ) {
                $credits = $this->endpoints['serp'][$search_engine][$search_type][$endpoint_type];
                
                // Adjust for multiple tasks
                if ( isset( $params[0] ) && is_array( $params[0] ) ) {
                    $credits *= count( $params );
                }
            }
        }
        
        // Keywords Data API endpoint calculation
        elseif ( $parts[0] === 'keywords_data' && isset( $parts[1] ) && isset( $parts[2] ) ) {
            $search_engine = $parts[1]; // e.g., google
            $endpoint_name = $parts[2]; // e.g., search_volume
            
            // Live vs task endpoints
            $endpoint_type = 'live';
            if ( isset( $parts[3] ) ) {
                if ( $parts[3] === 'task_post' ) {
                    $endpoint_type = 'task_post';
                } elseif ( $parts[3] === 'task_get' || strpos( $parts[3], 'tasks' ) === 0 ) {
                    $endpoint_type = 'task_get';
                }
            }
            
            // Get the credit cost from our pricing table
            if ( isset( $this->endpoints['keywords_data'][$search_engine][$endpoint_name][$endpoint_type] ) ) {
                $credits = $this->endpoints['keywords_data'][$search_engine][$endpoint_name][$endpoint_type];
                
                // Adjust for multiple keywords or tasks
                if ( isset( $params[0] ) && is_array( $params[0] ) ) {
                    $credits *= count( $params );
                }
            }
        }
        
        // On-Page API endpoint calculation
        elseif ( $parts[0] === 'on_page' ) {
            $endpoint_name = isset( $parts[1] ) ? $parts[1] : '';
            
            // Task-based endpoints
            if ( $endpoint_name === 'task_post' ) {
                $credits = $this->endpoints['on_page']['task_post'];
                
                // Adjust for multiple tasks
                if ( isset( $params[0] ) && is_array( $params[0] ) ) {
                    $credits *= count( $params );
                }
            } 
            elseif ( $endpoint_name === 'tasks' || strpos( $endpoint_name, 'task_get' ) === 0 ) {
                $credits = $this->endpoints['on_page']['task_get'];
            }
            elseif ( $endpoint_name === 'pages' ) {
                // Pages are charged per page
                $page_count = 0;
                if ( isset( $response['data']['tasks'][0]['result'] ) ) {
                    $page_count = count( $response['data']['tasks'][0]['result'] );
                }
                $credits = $this->endpoints['on_page']['pages'] * max( 1, $page_count );
            }
            elseif ( $endpoint_name === 'lighthouse' ) {
                $credits = $this->endpoints['on_page']['lighthouse'];
            }
            elseif ( $endpoint_name === 'content_parsing' ) {
                $credits = $this->endpoints['on_page']['content_parsing'];
            }
            elseif ( $endpoint_name === 'instant_pages' ) {
                // Instant pages are charged per page
                $page_count = 0;
                if ( isset( $response['data']['tasks'][0]['result'] ) ) {
                    $page_count = count( $response['data']['tasks'][0]['result'] );
                }
                $credits = $this->endpoints['on_page']['instant_pages'] * max( 1, $page_count );
            }
        }
        
        // Backlinks API endpoint calculation
        elseif ( $parts[0] === 'backlinks' ) {
            $endpoint_name = isset( $parts[1] ) ? $parts[1] : '';
            
            if ( $endpoint_name === 'summary' ) {
                $credits = $this->endpoints['backlinks']['summary'];
            }
            elseif ( $endpoint_name === 'backlinks' ) {
                // Backlinks are charged per backlink
                $backlink_count = 0;
                if ( isset( $response['data']['tasks'][0]['result'][0]['items'] ) ) {
                    $backlink_count = count( $response['data']['tasks'][0]['result'][0]['items'] );
                }
                $credits = $this->endpoints['backlinks']['backlinks'] * max( 1, $backlink_count );
            }
            elseif ( $endpoint_name === 'domain_pages' ) {
                $credits = $this->endpoints['backlinks']['domain_pages'];
            }
            elseif ( $endpoint_name === 'anchors' ) {
                $credits = $this->endpoints['backlinks']['anchors'];
            }
        }
        
        // Return credits as integer (multiply by 100 to convert to our credit system)
        return round( $credits * 100 );
    }

    /**
     * Generate sandbox SERP results.
     *
     * @param string $endpoint API endpoint.
     * @param array  $params   Request parameters.
     * @param string $task_id  The sandbox task ID.
     * @return array Mock SERP data.
     */
    private function generate_sandbox_serp_results( $endpoint, $params, $task_id ) {
        $result = array(
            'id' => $task_id,
            'status_code' => 20000,
            'status_message' => 'Sandbox mode: OK',
            'time' => date('Y-m-d\TH:i:s\Z'),
            'cost' => 0,
            'result_count' => 10,
            'path' => $endpoint,
            'data' => array(
                'api' => 'serp',
                'function' => $endpoint,
                'se' => 'google',
                'se_type' => 'organic',
                'location_code' => 2840,
                'language_code' => 'en',
                'keyword' => isset($params[0]['keyword']) ? $params[0]['keyword'] : 'sample keyword',
            ),
            'result' => array(),
        );

        // Generate mock organic results
        if (strpos($endpoint, 'organic') !== false) {
            $result['result'] = $this->generate_sandbox_organic_results(10);
        }
        // Generate mock local pack results
        elseif (strpos($endpoint, 'local_pack') !== false) {
            $result['result'] = $this->generate_sandbox_local_pack_results(3);
        }

        return $result;
    }

    /**
     * Generate sandbox organic search results.
     *
     * @param int $count Number of results to generate.
     * @return array Mock organic results.
     */
    private function generate_sandbox_organic_results($count) {
        $results = array();
        
        for ($i = 0; $i < $count; $i++) {
            $results[] = array(
                'type' => 'organic',
                'rank_group' => $i + 1,
                'rank_absolute' => $i + 1,
                'position' => 'left',
                'title' => 'Sample Result ' . ($i + 1),
                'url' => 'https://example.com/page-' . ($i + 1),
                'description' => 'This is a sample description for the search result ' . ($i + 1) . '. This is sandbox data, not real results.',
                'links' => array(
                    'self' => array(
                        'title' => 'Sample Result ' . ($i + 1),
                        'url' => 'https://example.com/page-' . ($i + 1),
                    ),
                ),
                'domain' => 'example.com',
                'breadcrumb' => 'example.com › category › page-' . ($i + 1),
            );
        }
        
        return $results;
    }

    /**
     * Generate sandbox local pack results.
     *
     * @param int $count Number of results to generate.
     * @return array Mock local pack results.
     */
    private function generate_sandbox_local_pack_results($count) {
        $results = array(
            'place_id' => 'sandbox_place_id_' . uniqid(),
            'cid' => 'sandbox_cid_' . uniqid(),
            'feature_id' => 'sandbox_feature_id_' . uniqid(),
            'title' => 'Sample Local Pack',
            'items' => array(),
        );
        
        for ($i = 0; $i < $count; $i++) {
            $results['items'][] = array(
                'type' => 'local_pack',
                'rank_group' => $i + 1,
                'rank_absolute' => $i + 1,
                'domain' => 'example.com',
                'title' => 'Local Business ' . ($i + 1),
                'url' => 'https://example.com/business-' . ($i + 1),
                'rating' => array(
                    'rating_value' => rand(30, 50) / 10,
                    'rating_count' => rand(5, 100),
                ),
                'address' => '123 Main St, Sample City, State',
                'phone' => '+1 (555) 123-45' . rand(10, 99),
                'categories' => array('Category ' . ($i + 1)),
            );
        }
        
        return $results;
    }

    /**
     * Generate sandbox keywords data.
     *
     * @param string $endpoint API endpoint.
     * @param array  $params   Request parameters.
     * @param string $task_id  The sandbox task ID.
     * @return array Mock keywords data.
     */
    private function generate_sandbox_keywords_data($endpoint, $params, $task_id) {
        // Will implement in next update
        return array();
    }
    
    /**
     * Generate sandbox on-page data.
     *
     * @param string $endpoint API endpoint.
     * @param array  $params   Request parameters.
     * @param string $task_id  The sandbox task ID.
     * @return array Mock on-page data.
     */
    private function generate_sandbox_on_page_data($endpoint, $params, $task_id) {
        // Will implement in next update
        return array();
    }
    
    /**
     * Generate sandbox backlinks data.
     *
     * @param string $endpoint API endpoint.
     * @param array  $params   Request parameters.
     * @param string $task_id  The sandbox task ID.
     * @return array Mock backlinks data.
     */
    private function generate_sandbox_backlinks_data($endpoint, $params, $task_id) {
        // Will implement in next update
        return array();
    }
    
    /**
     * Get Google SERP (Search Engine Results Page) data.
     *
     * @param array $params Search parameters.
     * @param bool  $live   Whether to use live API or task-based.
     * @return array Response data.
     */
    public function get_google_serp_organic($params, $live = true) {
        // Set default parameters
        $params = is_array($params) ? $params : array('keyword' => $params);
        $params = wp_parse_args($params, array(
            'keyword' => '',
            'location_code' => 2840, // United States
            'language_code' => 'en',
            'device' => 'desktop',
            'os' => 'windows',
        ));
        
        // Check if enough credits before making the request
        $endpoint = 'serp/google/organic' . ($live ? '/live' : '/task_post');
        $estimated_cost = $this->estimate_credits_for_endpoint($endpoint, array($params), array());
        
        if (!$this->sandbox_mode && !$this->check_credits($estimated_cost)) {
            return array(
                'success' => false,
                'error' => array(
                    'code' => 'insufficient_credits',
                    'message' => 'Not enough credits to perform this operation.'
                )
            );
        }
        
        // Format the request data
        $request_data = array($params);
        
        // Make the request (with caching if live)
        if ($live) {
            return $this->request_with_cache($endpoint, $request_data, 'POST');
        } else {
            return $this->make_request($endpoint, $request_data, 'POST');
        }
    }
    
    /**
     * Get Google Local Pack SERP data.
     *
     * @param array $params Search parameters.
     * @param bool  $live   Whether to use live API or task-based.
     * @return array Response data.
     */
    public function get_google_serp_local_pack($params, $live = true) {
        // Set default parameters
        $params = is_array($params) ? $params : array('keyword' => $params);
        $params = wp_parse_args($params, array(
            'keyword' => '',
            'location_code' => 2840, // United States
            'language_code' => 'en',
            'device' => 'desktop',
            'os' => 'windows',
        ));
        
        // Check if enough credits before making the request
        $endpoint = 'serp/google/local_pack' . ($live ? '/live' : '/task_post');
        $estimated_cost = $this->estimate_credits_for_endpoint($endpoint, array($params), array());
        
        if (!$this->sandbox_mode && !$this->check_credits($estimated_cost)) {
            return array(
                'success' => false,
                'error' => array(
                    'code' => 'insufficient_credits',
                    'message' => 'Not enough credits to perform this operation.'
                )
            );
        }
        
        // Format the request data
        $request_data = array($params);
        
        // Make the request (with caching if live)
        if ($live) {
            return $this->request_with_cache($endpoint, $request_data, 'POST');
        } else {
            return $this->make_request($endpoint, $request_data, 'POST');
        }
    }
    
    /**
     * Get task results for a previously created task.
     *
     * @param string $task_id The task ID to retrieve.
     * @return array Response data.
     */
    public function get_task_result($task_id) {
        // Extract API and search engine from task ID if possible
        $api = 'serp';
        $se = 'google';
        
        // Determine endpoint from task ID format if possible
        if (preg_match('/(\w+)-(\w+)-/', $task_id, $matches)) {
            $api = $matches[1];
            $se = $matches[2];
        }
        
        $endpoint = $api . '/' . $se . '/tasks_ready';
        
        // Make the request
        return $this->request_with_cache($endpoint . '/' . $task_id, array(), 'GET');
    }
    
    // Keywords Data API methods will be added in next update
    
    // On-Page API methods will be added in next update
    
    // Backlinks API methods will be added in next update
} 