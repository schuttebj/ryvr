<?php
/**
 * The JWT Service class.
 *
 * Handles JWT token generation and validation for parent-child communication.
 *
 * @package    Ryvr
 * @subpackage Ryvr/API/Services
 */

namespace Ryvr\API\Services;

use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

/**
 * The JWT Service class.
 *
 * This class provides methods for creating, validating, and managing JWT tokens
 * that enable secure communication between parent platform and child plugins.
 *
 * @package    Ryvr
 * @subpackage Ryvr/API/Services
 */
class JWT_Service {

    /**
     * JWT secret key option name.
     *
     * @var string
     */
    private $jwt_secret_option = 'ryvr_jwt_secret';

    /**
     * JWT token expiration time in seconds (default: 1 hour).
     *
     * @var int
     */
    private $token_expiration = 3600;

    /**
     * JWT algorithm.
     *
     * @var string
     */
    private $algorithm = 'HS256';

    /**
     * Allowed domains for child sites.
     *
     * @var array
     */
    private $allowed_domains = [];

    /**
     * Initialize the service.
     *
     * @return void
     */
    public function init() {
        // Load settings.
        $this->load_settings();

        // Generate a secret key if one doesn't exist.
        if (empty($this->get_jwt_secret())) {
            $this->generate_new_secret();
        }

        // Register REST API endpoints.
        add_action('rest_api_init', array($this, 'register_rest_routes'));

        // Add token validation to admin-ajax requests.
        add_action('admin_init', array($this, 'validate_child_token_on_admin_requests'));
    }

    /**
     * Load JWT settings.
     *
     * @return void
     */
    private function load_settings() {
        // Get configured token expiration (if set).
        $expiration = get_option('ryvr_jwt_expiration', null);
        if (!empty($expiration) && is_numeric($expiration)) {
            $this->token_expiration = (int) $expiration;
        }

        // Get allowed domains.
        $domains = get_option('ryvr_allowed_domains', '');
        if (!empty($domains)) {
            $this->allowed_domains = array_map('trim', explode(',', $domains));
        }
    }

    /**
     * Generate a new JWT secret.
     *
     * @return string The new secret.
     */
    public function generate_new_secret() {
        // Generate a secure random string (64 characters).
        $secret = bin2hex(random_bytes(32));
        
        // Save the secret.
        update_option($this->jwt_secret_option, $secret);
        
        return $secret;
    }

    /**
     * Get the JWT secret.
     *
     * @return string The JWT secret.
     */
    public function get_jwt_secret() {
        return get_option($this->jwt_secret_option, '');
    }

    /**
     * Create a JWT token for a child site.
     *
     * @param array $payload Additional data to include in the token.
     * @param int   $expiration Token expiration in seconds (optional).
     * @return string The JWT token.
     */
    public function create_token($payload = array(), $expiration = null) {
        if (empty($expiration)) {
            $expiration = $this->token_expiration;
        }

        $issued_at = time();
        $expiration_time = $issued_at + $expiration;

        $token_payload = array_merge(
            array(
                'iss' => get_site_url(), // Issuer (parent site URL).
                'iat' => $issued_at,     // Issued at time.
                'exp' => $expiration_time, // Expiration time.
                'nbf' => $issued_at,     // Not valid before issued time.
            ),
            $payload
        );

        return JWT::encode($token_payload, $this->get_jwt_secret(), $this->algorithm);
    }

    /**
     * Validate a JWT token from a child site.
     *
     * @param string $token The JWT token to validate.
     * @return object|WP_Error Decoded token data on success, WP_Error on failure.
     */
    public function validate_token($token) {
        try {
            // Decode the token.
            $decoded = JWT::decode($token, new Key($this->get_jwt_secret(), $this->algorithm));
            
            // Check if token has expired.
            if (isset($decoded->exp) && time() > $decoded->exp) {
                return new \WP_Error('token_expired', __('Token has expired.', 'ryvr-ai'));
            }
            
            // Validate issuer if present.
            if (isset($decoded->iss)) {
                // If we have allowed domains, check against them.
                if (!empty($this->allowed_domains)) {
                    $domain = parse_url($decoded->iss, PHP_URL_HOST);
                    if (!in_array($domain, $this->allowed_domains)) {
                        return new \WP_Error('invalid_issuer', __('Token issuer is not allowed.', 'ryvr-ai'));
                    }
                }
            }
            
            return $decoded;
        } catch (\Exception $e) {
            return new \WP_Error('token_validation_failed', $e->getMessage());
        }
    }

    /**
     * Register REST API routes for JWT authentication.
     *
     * @return void
     */
    public function register_rest_routes() {
        // Register endpoint for child plugins to request a token.
        register_rest_route('ryvr/v1', '/auth/token', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'rest_get_token'),
            'permission_callback' => array($this, 'rest_authenticate_request'),
        ));

        // Register endpoint to validate a token.
        register_rest_route('ryvr/v1', '/auth/validate', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'rest_validate_token'),
            'permission_callback' => '__return_true',
        ));

        // Register endpoint to revoke a token (for future use with token blacklisting).
        register_rest_route('ryvr/v1', '/auth/revoke', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'rest_revoke_token'),
            'permission_callback' => array($this, 'rest_authenticate_request'),
        ));
    }

    /**
     * REST API callback to get a token.
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response The response object.
     */
    public function rest_get_token($request) {
        $domain = $request->get_param('domain');
        $client_id = $request->get_param('client_id');
        
        // Validate domain if domain restrictions are enabled.
        if (!empty($this->allowed_domains) && $domain) {
            $request_domain = parse_url($domain, PHP_URL_HOST);
            if (!in_array($request_domain, $this->allowed_domains)) {
                return new \WP_REST_Response(
                    array('error' => __('Domain not authorized.', 'ryvr-ai')),
                    403
                );
            }
        }
        
        // Create custom payload.
        $payload = array(
            'domain' => $domain,
        );
        
        // Add client ID if available.
        if ($client_id) {
            $payload['client_id'] = $client_id;
        }
        
        // Generate token.
        $token = $this->create_token($payload);
        
        return new \WP_REST_Response(
            array(
                'token' => $token,
                'expires_in' => $this->token_expiration,
            ),
            200
        );
    }

    /**
     * REST API callback to validate a token.
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response The response object.
     */
    public function rest_validate_token($request) {
        $token = $request->get_param('token');
        
        if (empty($token)) {
            return new \WP_REST_Response(
                array('error' => __('No token provided.', 'ryvr-ai')),
                400
            );
        }
        
        $result = $this->validate_token($token);
        
        if (is_wp_error($result)) {
            return new \WP_REST_Response(
                array('error' => $result->get_error_message()),
                401
            );
        }
        
        return new \WP_REST_Response(
            array(
                'valid' => true,
                'payload' => $result,
            ),
            200
        );
    }

    /**
     * REST API callback to revoke a token.
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response The response object.
     */
    public function rest_revoke_token($request) {
        $token = $request->get_param('token');
        
        if (empty($token)) {
            return new \WP_REST_Response(
                array('error' => __('No token provided.', 'ryvr-ai')),
                400
            );
        }
        
        // In the future, we could implement a token blacklist here
        // For now, we just validate the token
        $result = $this->validate_token($token);
        
        if (is_wp_error($result)) {
            return new \WP_REST_Response(
                array('error' => $result->get_error_message()),
                401
            );
        }
        
        return new \WP_REST_Response(
            array('revoked' => true),
            200
        );
    }

    /**
     * REST API permission callback to authenticate request.
     *
     * @param WP_REST_Request $request The request object.
     * @return bool Whether the request is authenticated.
     */
    public function rest_authenticate_request($request) {
        // Check for token in Authorization header.
        $auth_header = $request->get_header('Authorization');
        
        if ($auth_header && strpos($auth_header, 'Bearer ') === 0) {
            $token = substr($auth_header, 7);
            $result = $this->validate_token($token);
            
            if (!is_wp_error($result)) {
                return true;
            }
        }
        
        // For initial token requests, allow alternative authentication methods.
        // This could be API key, domain validation, etc.
        $api_key = $request->get_param('api_key');
        
        if ($api_key) {
            // Check if this is a valid API key in our system.
            global $wpdb;
            $table = $wpdb->prefix . 'ryvr_api_keys';
            
            $key_exists = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM $table WHERE api_key = %s AND is_active = 1",
                    $api_key
                )
            );
            
            if ($key_exists) {
                return true;
            }
        }
        
        // Default domain-based authentication for initial setup.
        $domain = $request->get_param('domain');
        
        if ($domain && !empty($this->allowed_domains)) {
            $request_domain = parse_url($domain, PHP_URL_HOST);
            if (in_array($request_domain, $this->allowed_domains)) {
                return true;
            }
        }
        
        return current_user_can('manage_options');
    }

    /**
     * Validate child token on admin AJAX requests.
     *
     * @return void
     */
    public function validate_child_token_on_admin_requests() {
        // Only check AJAX requests from child sites.
        if (!wp_doing_ajax() || !isset($_REQUEST['ryvr_child_token'])) {
            return;
        }
        
        $token = sanitize_text_field($_REQUEST['ryvr_child_token']);
        $result = $this->validate_token($token);
        
        if (is_wp_error($result)) {
            wp_send_json_error(
                array('message' => $result->get_error_message()),
                401
            );
            exit;
        }
        
        // Store the decoded token for later use.
        $GLOBALS['ryvr_child_token_data'] = $result;
    }

    /**
     * Check if token exists and is valid for a specific domain.
     *
     * @param string $domain Domain to check.
     * @return bool Whether domain has a valid token.
     */
    public function domain_has_valid_token($domain) {
        // In a real implementation, we might store tokens in the database
        // For now, we just check if the domain is allowed
        if (empty($this->allowed_domains)) {
            return true; // If no domains are restricted, all are allowed
        }
        
        return in_array($domain, $this->allowed_domains);
    }

    /**
     * Allow a new domain for JWT authentication.
     *
     * @param string $domain Domain to allow.
     * @return bool Whether the domain was added.
     */
    public function add_allowed_domain($domain) {
        if (empty($domain)) {
            return false;
        }
        
        $domain = trim($domain);
        
        if (in_array($domain, $this->allowed_domains)) {
            return true; // Already exists
        }
        
        $this->allowed_domains[] = $domain;
        $domains_str = implode(',', $this->allowed_domains);
        
        update_option('ryvr_allowed_domains', $domains_str);
        
        return true;
    }

    /**
     * Remove a domain from allowed domains.
     *
     * @param string $domain Domain to remove.
     * @return bool Whether the domain was removed.
     */
    public function remove_allowed_domain($domain) {
        $key = array_search($domain, $this->allowed_domains);
        
        if ($key !== false) {
            unset($this->allowed_domains[$key]);
            $domains_str = implode(',', $this->allowed_domains);
            
            update_option('ryvr_allowed_domains', $domains_str);
            
            return true;
        }
        
        return false;
    }
} 