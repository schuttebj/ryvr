<?php
/**
 * The Parent Connector class.
 *
 * Handles connection to the parent platform.
 *
 * @package    Ryvr_Client
 * @subpackage Ryvr_Client/API
 */

namespace Ryvr_Client\API;

/**
 * The Parent Connector class.
 *
 * This class handles authentication and connection to the parent platform.
 *
 * @package    Ryvr_Client
 * @subpackage Ryvr_Client/API
 */
class Parent_Connector {

    /**
     * Last error message.
     *
     * @var \WP_Error
     */
    private $last_error = null;

    /**
     * Authentication token.
     *
     * @var string
     */
    private $auth_token = '';

    /**
     * Token expiration time.
     *
     * @var int
     */
    private $token_expiration = 0;

    /**
     * Initialize the connector.
     *
     * @return void
     */
    public function init() {
        // Load stored token.
        $this->auth_token = get_transient( 'ryvr_client_auth_token' );
        $this->token_expiration = (int) get_option( 'ryvr_client_token_expiration', 0 );

        // Check token expiration.
        if ( $this->token_expiration > 0 && $this->token_expiration < time() ) {
            // Token expired, clear it.
            $this->clear_token();

            // Try to refresh the token automatically.
            $this->refresh_token();
        }

        // Register health check endpoint.
        add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );

        // Set up heartbeat.
        add_action( 'wp', [ $this, 'schedule_heartbeat' ] );
        add_action( 'ryvr_client_heartbeat', [ $this, 'send_heartbeat' ] );
    }

    /**
     * Register REST API routes.
     *
     * @return void
     */
    public function register_rest_routes() {
        register_rest_route( 'ryvr-client/v1', '/health-check', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'health_check' ],
            'permission_callback' => [ $this, 'validate_parent_request' ],
        ] );

        register_rest_route( 'ryvr-client/v1', '/execute-task', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'execute_task' ],
            'permission_callback' => [ $this, 'validate_parent_request' ],
        ] );
    }

    /**
     * Schedule heartbeat event.
     *
     * @return void
     */
    public function schedule_heartbeat() {
        if ( ! wp_next_scheduled( 'ryvr_client_heartbeat' ) ) {
            wp_schedule_event( time(), 'hourly', 'ryvr_client_heartbeat' );
        }
    }

    /**
     * Send heartbeat to parent platform.
     *
     * @return void
     */
    public function send_heartbeat() {
        if ( ! ryvr_client_is_connected() ) {
            return;
        }

        $api_client = ryvr_client()->get_component( 'api_client' );
        if ( ! $api_client ) {
            return;
        }

        $api_client->post( 'client/heartbeat', [
            'site_info' => ryvr_client_get_site_info(),
            'status'    => [
                'timestamp' => current_time( 'timestamp' ),
                'task_running' => ryvr_client_is_task_running(),
            ],
        ] );
    }

    /**
     * Validate a request from the parent platform.
     *
     * @param \WP_REST_Request $request Request object.
     * @return bool|\WP_Error
     */
    public function validate_parent_request( $request ) {
        // Get parent URL.
        $parent_url = ryvr_client_get_parent_url();
        if ( empty( $parent_url ) ) {
            return new \WP_Error(
                'missing_parent_url',
                __( 'Parent platform URL is not configured.', 'ryvr-client' )
            );
        }

        // Get API key.
        $api_key = ryvr_client_get_api_key();
        if ( empty( $api_key ) ) {
            return new \WP_Error(
                'missing_api_key',
                __( 'API key is not configured.', 'ryvr-client' )
            );
        }

        // Get authorization header.
        $authorization = $request->get_header( 'authorization' );
        if ( empty( $authorization ) ) {
            return new \WP_Error(
                'missing_authorization',
                __( 'Authorization header is missing.', 'ryvr-client' )
            );
        }

        // Extract token from header.
        $token = str_replace( 'Bearer ', '', $authorization );
        if ( empty( $token ) ) {
            return new \WP_Error(
                'invalid_token_format',
                __( 'Invalid token format.', 'ryvr-client' )
            );
        }

        // Validate token.
        $api_client = ryvr_client()->get_component( 'api_client' );
        if ( ! $api_client ) {
            return new \WP_Error(
                'api_client_missing',
                __( 'API client component not found.', 'ryvr-client' )
            );
        }

        // Send validation request to parent.
        $validation = $api_client->post( 'auth/validate', [
            'token' => $token,
        ] );

        if ( is_wp_error( $validation ) ) {
            return $validation;
        }

        // Check validation response.
        if ( ! isset( $validation['success'] ) || ! $validation['success'] ) {
            return new \WP_Error(
                'invalid_token',
                __( 'Invalid token.', 'ryvr-client' )
            );
        }

        return true;
    }

    /**
     * Health check endpoint handler.
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response
     */
    public function health_check( $request ) {
        return rest_ensure_response( [
            'success' => true,
            'message' => __( 'Ryvr client is online and healthy.', 'ryvr-client' ),
            'site_info' => ryvr_client_get_site_info(),
            'status' => [
                'timestamp' => current_time( 'timestamp' ),
                'task_running' => ryvr_client_is_task_running(),
            ],
        ] );
    }

    /**
     * Execute task endpoint handler.
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response|\WP_Error
     */
    public function execute_task( $request ) {
        // Get task data.
        $task_data = $request->get_json_params();
        if ( empty( $task_data ) || ! isset( $task_data['task_type'] ) ) {
            return new \WP_Error(
                'invalid_task_data',
                __( 'Invalid task data.', 'ryvr-client' )
            );
        }

        // Check if a task is already running.
        if ( ryvr_client_is_task_running() ) {
            return new \WP_Error(
                'task_running',
                __( 'A task is already running on this client.', 'ryvr-client' )
            );
        }

        // Set task running flag.
        set_transient( 'ryvr_client_task_running', true, 300 ); // 5 minute timeout.

        // Log task start.
        ryvr_client_log(
            sprintf(
                'Starting task: %s (ID: %s)',
                $task_data['task_type'],
                isset( $task_data['task_id'] ) ? $task_data['task_id'] : 'unknown'
            ),
            'info'
        );

        // To be implemented: Task execution logic.
        // This would depend on task type, but for now we'll just return success.

        // Clear task running flag.
        delete_transient( 'ryvr_client_task_running' );

        // Log task completion.
        ryvr_client_log(
            sprintf(
                'Completed task: %s (ID: %s)',
                $task_data['task_type'],
                isset( $task_data['task_id'] ) ? $task_data['task_id'] : 'unknown'
            ),
            'info'
        );

        return rest_ensure_response( [
            'success' => true,
            'message' => __( 'Task executed successfully.', 'ryvr-client' ),
            'task_id' => isset( $task_data['task_id'] ) ? $task_data['task_id'] : 'unknown',
        ] );
    }

    /**
     * Authenticate with parent platform.
     *
     * @param string $parent_url Parent platform URL.
     * @param string $api_key    API key.
     * @return string|\WP_Error Authentication token or error.
     */
    public function authenticate( $parent_url, $api_key ) {
        // Reset last error.
        $this->last_error = null;

        // Check if we already have a valid token.
        if ( ! empty( $this->auth_token ) && $this->token_expiration > time() ) {
            return $this->auth_token;
        }

        // Create temporary API client for authentication.
        $api_client = new API_Client();

        // Build request URL.
        $api_url = trailingslashit( $parent_url ) . 'wp-json/ryvr/v1/auth/token';

        // Set up request arguments.
        $request_args = [
            'method'  => 'POST',
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body'    => wp_json_encode( [
                'api_key'   => $api_key,
                'site_info' => ryvr_client_get_site_info(),
            ] ),
        ];

        // Send the request.
        $response = wp_remote_post( $api_url, $request_args );

        // Check for errors.
        if ( is_wp_error( $response ) ) {
            $this->last_error = $response;
            ryvr_client_log( 'Authentication failed: ' . $response->get_error_message(), 'error' );
            return $response;
        }

        // Check response code.
        $response_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );
        $response_data = json_decode( $response_body, true );

        if ( $response_code < 200 || $response_code >= 300 ) {
            $error_message = isset( $response_data['message'] )
                ? $response_data['message']
                : sprintf( __( 'HTTP Error: %d', 'ryvr-client' ), $response_code );

            $this->last_error = new \WP_Error(
                'authentication_failed',
                $error_message,
                [
                    'status' => $response_code,
                    'response' => $response_data,
                ]
            );

            ryvr_client_log( 'Authentication failed: ' . $error_message, 'error' );
            return $this->last_error;
        }

        // Check for token in response.
        if ( ! isset( $response_data['token'] ) ) {
            $this->last_error = new \WP_Error(
                'invalid_response',
                __( 'No token received from parent platform.', 'ryvr-client' )
            );
            ryvr_client_log( 'Authentication failed: ' . $this->last_error->get_error_message(), 'error' );
            return $this->last_error;
        }

        // Store token and expiration.
        $this->auth_token = $response_data['token'];
        $this->token_expiration = isset( $response_data['expires'] )
            ? (int) $response_data['expires']
            : ( time() + 3600 ); // Default 1 hour expiration.

        // Save token and expiration.
        set_transient( 'ryvr_client_auth_token', $this->auth_token, $this->token_expiration - time() );
        update_option( 'ryvr_client_token_expiration', $this->token_expiration );

        // Log success.
        ryvr_client_log( 'Authentication successful. Token expires at ' . date( 'Y-m-d H:i:s', $this->token_expiration ), 'info' );

        return $this->auth_token;
    }

    /**
     * Refresh the authentication token.
     *
     * @return string|\WP_Error Authentication token or error.
     */
    public function refresh_token() {
        $parent_url = ryvr_client_get_parent_url();
        $api_key = ryvr_client_get_api_key();

        if ( empty( $parent_url ) || empty( $api_key ) ) {
            $this->last_error = new \WP_Error(
                'missing_credentials',
                __( 'Parent URL or API key is missing.', 'ryvr-client' )
            );
            return $this->last_error;
        }

        return $this->authenticate( $parent_url, $api_key );
    }

    /**
     * Clear the authentication token.
     *
     * @return void
     */
    public function clear_token() {
        $this->auth_token = '';
        $this->token_expiration = 0;
        delete_transient( 'ryvr_client_auth_token' );
        update_option( 'ryvr_client_token_expiration', 0 );
    }

    /**
     * Get the last error message.
     *
     * @return \WP_Error|null
     */
    public function get_last_error() {
        return $this->last_error;
    }
} 