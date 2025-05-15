<?php
/**
 * The API Client class.
 *
 * Handles API requests to the parent platform.
 *
 * @package    Ryvr_Client
 * @subpackage Ryvr_Client/API
 */

namespace Ryvr_Client\API;

/**
 * The API Client class.
 *
 * This class handles sending API requests to the parent platform.
 *
 * @package    Ryvr_Client
 * @subpackage Ryvr_Client/API
 */
class API_Client {

    /**
     * Last error message.
     *
     * @var \WP_Error
     */
    private $last_error = null;

    /**
     * Initialize the client.
     *
     * @return void
     */
    public function init() {
        // Nothing to initialize at this point.
    }

    /**
     * Send a request to the parent platform.
     *
     * @param string $endpoint API endpoint.
     * @param array  $args     Request arguments.
     * @return mixed|\WP_Error
     */
    public function send_request( $endpoint, $args = [] ) {
        // Reset last error.
        $this->last_error = null;

        // Get parent URL and auth token.
        $parent_url = ryvr_client_get_parent_url();
        $auth_token = ryvr_client_get_auth_token();

        if ( empty( $parent_url ) ) {
            $this->last_error = new \WP_Error(
                'missing_parent_url',
                __( 'Parent platform URL is not configured.', 'ryvr-client' )
            );
            ryvr_client_log( 'API request failed: ' . $this->last_error->get_error_message(), 'error' );
            return $this->last_error;
        }

        if ( empty( $auth_token ) && strpos( $endpoint, 'auth/' ) === false ) {
            $this->last_error = new \WP_Error(
                'missing_auth_token',
                __( 'Authentication token is missing. Please reconnect to the parent platform.', 'ryvr-client' )
            );
            ryvr_client_log( 'API request failed: ' . $this->last_error->get_error_message(), 'error' );
            return $this->last_error;
        }

        // Build request URL.
        $api_url = trailingslashit( $parent_url ) . 'wp-json/ryvr/v1/' . ltrim( $endpoint, '/' );

        // Set up request arguments.
        $request_args = wp_parse_args( $args, [
            'method'  => 'GET',
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
        ] );

        // Add authentication token if available and not an auth request.
        if ( ! empty( $auth_token ) && strpos( $endpoint, 'auth/' ) === false ) {
            $request_args['headers']['Authorization'] = 'Bearer ' . $auth_token;
        }

        // Add site information to all requests.
        $request_args['headers']['X-Ryvr-Client-Info'] = base64_encode( json_encode( ryvr_client_get_site_info() ) );

        // For POST, PUT, etc. with a body, encode it as JSON.
        if ( isset( $request_args['body'] ) && is_array( $request_args['body'] ) ) {
            $request_args['body'] = wp_json_encode( $request_args['body'] );
        }

        // Log the request.
        ryvr_client_log(
            sprintf(
                'Sending %s request to %s',
                $request_args['method'],
                $api_url
            ),
            'debug'
        );

        // Send the request.
        $response = wp_remote_request( $api_url, $request_args );

        // Check for errors.
        if ( is_wp_error( $response ) ) {
            $this->last_error = $response;
            ryvr_client_log( 'API request failed: ' . $response->get_error_message(), 'error' );
            return $response;
        }

        // Check response code.
        $response_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );
        $response_data = json_decode( $response_body, true );

        // Log the response code.
        ryvr_client_log(
            sprintf(
                'Received response %d from %s',
                $response_code,
                $api_url
            ),
            'debug'
        );

        if ( $response_code < 200 || $response_code >= 300 ) {
            $error_message = isset( $response_data['message'] )
                ? $response_data['message']
                : sprintf( __( 'HTTP Error: %d', 'ryvr-client' ), $response_code );

            $this->last_error = new \WP_Error(
                'api_error',
                $error_message,
                [
                    'status' => $response_code,
                    'response' => $response_data,
                ]
            );

            ryvr_client_log( 'API request failed: ' . $error_message, 'error' );
            return $this->last_error;
        }

        // Return response data if successful.
        return $response_data;
    }

    /**
     * Get the last error message.
     *
     * @return \WP_Error|null
     */
    public function get_last_error() {
        return $this->last_error;
    }

    /**
     * Get data from parent platform.
     *
     * @param string $endpoint API endpoint.
     * @param array  $params   Query parameters.
     * @return mixed|\WP_Error
     */
    public function get( $endpoint, $params = [] ) {
        return $this->send_request( $endpoint, [
            'method' => 'GET',
            'body'   => $params,
        ] );
    }

    /**
     * Post data to parent platform.
     *
     * @param string $endpoint API endpoint.
     * @param array  $data     Data to send.
     * @return mixed|\WP_Error
     */
    public function post( $endpoint, $data = [] ) {
        return $this->send_request( $endpoint, [
            'method' => 'POST',
            'body'   => $data,
        ] );
    }

    /**
     * Update data on parent platform.
     *
     * @param string $endpoint API endpoint.
     * @param array  $data     Data to send.
     * @return mixed|\WP_Error
     */
    public function put( $endpoint, $data = [] ) {
        return $this->send_request( $endpoint, [
            'method' => 'PUT',
            'body'   => $data,
        ] );
    }

    /**
     * Delete data on parent platform.
     *
     * @param string $endpoint API endpoint.
     * @param array  $data     Data to send.
     * @return mixed|\WP_Error
     */
    public function delete( $endpoint, $data = [] ) {
        return $this->send_request( $endpoint, [
            'method' => 'DELETE',
            'body'   => $data,
        ] );
    }
} 