<?php

namespace Ryvr\Platform;

/**
 * Class responsible for authentication management in the Ryvr AI Platform.
 *
 * @since      1.0.0
 * @package    Ryvr\Platform
 */
class Auth_Manager {

    /**
     * The session timeout in seconds (default: 2 hours)
     * 
     * @var int
     */
    private $session_timeout = 7200;
    
    /**
     * The database manager instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      Database_Manager    $db_manager    The database manager instance.
     */
    private $db_manager;
    
    /**
     * The user manager instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      User_Manager    $user_manager    The user manager instance.
     */
    private $user_manager;
    
    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param    Database_Manager    $db_manager    The database manager instance.
     * @param    User_Manager        $user_manager  The user manager instance.
     */
    public function __construct( $db_manager, $user_manager ) {
        $this->db_manager = $db_manager;
        $this->user_manager = $user_manager;
        
        // Initialize session handling
        add_action( 'init', array( $this, 'init_session' ) );
        add_action( 'wp_login', array( $this, 'on_user_login' ), 10, 2 );
        add_action( 'wp_logout', array( $this, 'on_user_logout' ) );
        
        // AJAX actions for session management
        add_action( 'wp_ajax_ryvr_extend_session', array( $this, 'ajax_extend_session' ) );
        
        // MFA handlers
        add_action( 'wp_ajax_ryvr_verify_mfa', array( $this, 'ajax_verify_mfa' ) );
        add_action( 'wp_ajax_ryvr_setup_mfa', array( $this, 'ajax_setup_mfa' ) );
        add_action( 'wp_ajax_ryvr_disable_mfa', array( $this, 'ajax_disable_mfa' ) );
        
        // Get session timeout from settings
        $timeout = get_option( 'ryvr_session_timeout', 7200 );
        $this->session_timeout = intval( $timeout );
    }
    
    /**
     * Initialize the session
     * 
     * @since 1.0.0
     * @return void
     */
    public function init_session() {
        if ( !session_id() && !headers_sent() ) {
            session_start();
        }
        
        // Check if user is logged in and verify session validity
        if ( is_user_logged_in() ) {
            $this->verify_session();
        }
    }
    
    /**
     * Verify the current session is valid and not expired
     * 
     * @since 1.0.0
     * @return bool True if session is valid, false otherwise
     */
    public function verify_session() {
        // Skip for AJAX session extension requests
        if ( defined( 'DOING_AJAX' ) && DOING_AJAX && 
             isset( $_REQUEST['action'] ) && $_REQUEST['action'] === 'ryvr_extend_session' ) {
            return true;
        }
        
        // Check if MFA verification is required
        if ( isset( $_SESSION['ryvr_mfa_required'] ) && $_SESSION['ryvr_mfa_required'] === true ) {
            // Only allow access to MFA verification page
            if ( !is_page( 'mfa-verification' ) ) {
                wp_redirect( home_url( '/mfa-verification/' ) );
                exit;
            }
            return true;
        }
        
        $user_id = get_current_user_id();
        
        // Check if user account is active
        if ( !$this->user_manager->is_account_active( $user_id ) ) {
            wp_logout();
            wp_redirect( home_url( '/login/?reason=account_suspended' ) );
            exit;
        }
        
        // Check IP restrictions
        if ( !$this->is_ip_allowed( $user_id ) ) {
            wp_logout();
            wp_redirect( home_url( '/login/?reason=ip_restricted' ) );
            exit;
        }
        
        // Check if session exists
        if ( !isset( $_SESSION['ryvr_session_started'] ) ) {
            // No session data - create new session
            $this->create_session( $user_id );
            return true;
        }
        
        // Check if session has expired
        $last_activity = isset( $_SESSION['ryvr_last_activity'] ) ? $_SESSION['ryvr_last_activity'] : 0;
        $current_time = time();
        
        if ( ($current_time - $last_activity) > $this->session_timeout ) {
            // Session expired - log user out
            wp_logout();
            wp_redirect( home_url( '/login/?reason=session_expired' ) );
            exit;
        }
        
        // Update last activity time
        $_SESSION['ryvr_last_activity'] = $current_time;
        return true;
    }
    
    /**
     * Create a new session for the user
     * 
     * @since 1.0.0
     * @param int $user_id The user ID
     * @return void
     */
    public function create_session( $user_id ) {
        $_SESSION['ryvr_session_started'] = true;
        $_SESSION['ryvr_last_activity'] = time();
        $_SESSION['ryvr_user_id'] = $user_id;
        
        // Generate and store a session token
        $session_token = wp_generate_password( 64, false );
        $_SESSION['ryvr_session_token'] = $session_token;
        
        // Store session in database for multi-device management
        $this->store_session( $user_id, $session_token );
    }
    
    /**
     * Store session information in the database
     * 
     * @since 1.0.0
     * @param int    $user_id The user ID
     * @param string $token   The session token
     * @return void
     */
    private function store_session( $user_id, $token ) {
        global $wpdb;
        
        $table = $this->db_manager->get_table_name( 'sessions' );
        $data = array(
            'user_id'        => $user_id,
            'token'          => $token,
            'ip_address'     => $_SERVER['REMOTE_ADDR'],
            'user_agent'     => $_SERVER['HTTP_USER_AGENT'],
            'last_activity'  => current_time( 'mysql' ),
            'created_at'     => current_time( 'mysql' ),
        );
        
        $wpdb->insert( $table, $data );
    }
    
    /**
     * Handle user login - modified to check for MFA
     * 
     * @since 1.0.0
     * @param string  $user_login Username
     * @param WP_User $user       WP_User object
     * @return void
     */
    public function on_user_login( $user_login, $user ) {
        // Clear any existing session
        if ( session_id() ) {
            session_regenerate_id( true );
        }
        
        // Check if MFA is enabled
        if ( $this->is_mfa_enabled( $user->ID ) ) {
            // Create a temporary session for MFA verification
            $_SESSION['ryvr_mfa_required'] = true;
            $_SESSION['ryvr_mfa_user_id'] = $user->ID;
            
            // Redirect to MFA verification page
            wp_redirect( home_url( '/mfa-verification/' ) );
            exit;
        }
        
        // Create a new session
        $this->create_session( $user->ID );
        
        // Log the login
        Logger::log( 'auth', 'User login: ' . $user_login );
    }
    
    /**
     * Handle user logout
     * 
     * @since 1.0.0
     * @return void
     */
    public function on_user_logout() {
        if ( isset( $_SESSION['ryvr_session_token'] ) ) {
            // Remove session from database
            $this->remove_session( $_SESSION['ryvr_session_token'] );
        }
        
        // Clear session data
        $this->clear_session();
    }
    
    /**
     * Remove a session from the database
     * 
     * @since 1.0.0
     * @param string $token The session token
     * @return void
     */
    private function remove_session( $token ) {
        global $wpdb;
        
        $table = $this->db_manager->get_table_name( 'sessions' );
        $wpdb->delete( $table, array( 'token' => $token ) );
    }
    
    /**
     * Clear session data
     * 
     * @since 1.0.0
     * @return void
     */
    private function clear_session() {
        // Unset all session variables
        $_SESSION = array();
        
        // If it's desired to kill the session, also delete the session cookie.
        if ( ini_get( "session.use_cookies" ) ) {
            $params = session_get_cookie_params();
            setcookie( session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        // Finally, destroy the session
        if ( session_id() ) {
            session_destroy();
        }
    }
    
    /**
     * AJAX handler to extend the session
     * 
     * @since 1.0.0
     * @return void
     */
    public function ajax_extend_session() {
        // Verify nonce
        check_ajax_referer( 'ryvr_session_nonce', 'nonce' );
        
        // Update last activity time
        $_SESSION['ryvr_last_activity'] = time();
        
        // Update session in database
        if ( isset( $_SESSION['ryvr_session_token'] ) ) {
            global $wpdb;
            $table = $this->db_manager->get_table_name( 'sessions' );
            $wpdb->update(
                $table,
                array( 'last_activity' => current_time( 'mysql' ) ),
                array( 'token' => $_SESSION['ryvr_session_token'] )
            );
        }
        
        wp_send_json_success( array(
            'message' => 'Session extended',
            'expires' => time() + $this->session_timeout
        ) );
    }
    
    /**
     * Get all active sessions for a user
     * 
     * @since 1.0.0
     * @param int $user_id The user ID
     * @return array Array of session data
     */
    public function get_user_sessions( $user_id ) {
        global $wpdb;
        
        $table = $this->db_manager->get_table_name( 'sessions' );
        
        // Get sessions less than timeout threshold old
        $timeout_threshold = date( 'Y-m-d H:i:s', time() - $this->session_timeout );
        
        $query = $wpdb->prepare(
            "SELECT * FROM $table 
             WHERE user_id = %d 
             AND last_activity > %s
             ORDER BY last_activity DESC",
            $user_id,
            $timeout_threshold
        );
        
        return $wpdb->get_results( $query );
    }
    
    /**
     * Terminate a specific session
     * 
     * @since 1.0.0
     * @param int    $user_id The user ID (for permission check)
     * @param string $token   The session token to terminate
     * @return bool True on success, false on failure
     */
    public function terminate_session( $user_id, $token ) {
        global $wpdb;
        
        $table = $this->db_manager->get_table_name( 'sessions' );
        
        // Check if the session belongs to the user
        $session = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE token = %s",
                $token
            )
        );
        
        if ( !$session || $session->user_id != $user_id ) {
            return false;
        }
        
        // Remove the session
        $this->remove_session( $token );
        
        // Log the termination
        Logger::log( 'auth', 'Session terminated for user #' . $user_id );
        
        return true;
    }
    
    /**
     * Clean up expired sessions
     * 
     * @since 1.0.0
     * @return int Number of sessions removed
     */
    public function cleanup_expired_sessions() {
        global $wpdb;
        
        $table = $this->db_manager->get_table_name( 'sessions' );
        $timeout_threshold = date( 'Y-m-d H:i:s', time() - $this->session_timeout );
        
        $query = $wpdb->prepare(
            "DELETE FROM $table WHERE last_activity < %s",
            $timeout_threshold
        );
        
        $wpdb->query( $query );
        return $wpdb->rows_affected;
    }
    
    /**
     * Set the session timeout value
     * 
     * @since 1.0.0
     * @param int $seconds Timeout in seconds
     * @return void
     */
    public function set_session_timeout( $seconds ) {
        $this->session_timeout = intval( $seconds );
        update_option( 'ryvr_session_timeout', $this->session_timeout );
    }
    
    /**
     * Check if the current IP is allowed for a user
     * 
     * @since 1.0.0
     * @param int $user_id The user ID
     * @return bool True if allowed, false if restricted
     */
    public function is_ip_allowed( $user_id ) {
        // Get user's IP restrictions
        $ip_restrictions = get_user_meta( $user_id, 'ryvr_ip_restrictions', true );
        
        // If no restrictions are set, allow access
        if ( empty( $ip_restrictions ) || !is_array( $ip_restrictions ) ) {
            return true;
        }
        
        // Get current IP
        $current_ip = $_SERVER['REMOTE_ADDR'];
        
        // Check if restrictions are whitelist or blacklist mode
        $mode = isset( $ip_restrictions['mode'] ) ? $ip_restrictions['mode'] : 'whitelist';
        $ip_list = isset( $ip_restrictions['ips'] ) ? $ip_restrictions['ips'] : array();
        
        // Check if IP is in the list
        $ip_found = $this->is_ip_in_list( $current_ip, $ip_list );
        
        // In whitelist mode, IP must be in the list to allow access
        // In blacklist mode, IP must NOT be in the list to allow access
        return ( $mode === 'whitelist' ) ? $ip_found : !$ip_found;
    }
    
    /**
     * Check if an IP is in a list of IPs or CIDR ranges
     * 
     * @since 1.0.0
     * @param string $ip      The IP to check
     * @param array  $ip_list List of IPs or CIDR ranges
     * @return bool True if IP is in the list, false otherwise
     */
    private function is_ip_in_list( $ip, $ip_list ) {
        if ( empty( $ip_list ) ) {
            return false;
        }
        
        // Convert IP to long for easier comparison
        $ip_long = ip2long( $ip );
        
        foreach ( $ip_list as $range ) {
            // Check if this is a CIDR range
            if ( strpos( $range, '/' ) !== false ) {
                list( $subnet, $mask ) = explode( '/', $range );
                
                // Convert subnet to long
                $subnet_long = ip2long( $subnet );
                $mask_long = ~((1 << (32 - $mask)) - 1);
                
                // Check if IP is in subnet
                if ( ($ip_long & $mask_long) === ($subnet_long & $mask_long) ) {
                    return true;
                }
            } 
            // Check for direct IP match
            else if ( $ip === $range ) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Set IP restrictions for a user
     * 
     * @since 1.0.0
     * @param int    $user_id The user ID
     * @param string $mode    Restriction mode ('whitelist' or 'blacklist')
     * @param array  $ip_list List of IPs or CIDR ranges
     * @return bool True on success, false on failure
     */
    public function set_ip_restrictions( $user_id, $mode, $ip_list ) {
        // Validate mode
        if ( !in_array( $mode, array( 'whitelist', 'blacklist' ) ) ) {
            return false;
        }
        
        // Validate IP list
        if ( !is_array( $ip_list ) ) {
            return false;
        }
        
        // Filter out invalid IPs
        $valid_ips = array();
        foreach ( $ip_list as $ip ) {
            // Check if it's a CIDR range
            if ( strpos( $ip, '/' ) !== false ) {
                list( $subnet, $mask ) = explode( '/', $ip );
                if ( filter_var( $subnet, FILTER_VALIDATE_IP ) && $mask >= 0 && $mask <= 32 ) {
                    $valid_ips[] = $ip;
                }
            }
            // Check if it's a valid IP
            else if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                $valid_ips[] = $ip;
            }
        }
        
        // Store restrictions
        $restrictions = array(
            'mode' => $mode,
            'ips'  => $valid_ips
        );
        
        update_user_meta( $user_id, 'ryvr_ip_restrictions', $restrictions );
        
        // Log the change
        Logger::log( 'auth', 'IP restrictions updated for user #' . $user_id );
        
        return true;
    }
    
    /**
     * Get IP restrictions for a user
     * 
     * @since 1.0.0
     * @param int $user_id The user ID
     * @return array Restrictions array with 'mode' and 'ips' keys
     */
    public function get_ip_restrictions( $user_id ) {
        $restrictions = get_user_meta( $user_id, 'ryvr_ip_restrictions', true );
        
        if ( empty( $restrictions ) || !is_array( $restrictions ) ) {
            // Default to whitelist mode with empty list (allow all)
            return array(
                'mode' => 'whitelist',
                'ips'  => array()
            );
        }
        
        return $restrictions;
    }
    
    /**
     * Remove all IP restrictions for a user
     * 
     * @since 1.0.0
     * @param int $user_id The user ID
     * @return bool True on success
     */
    public function remove_ip_restrictions( $user_id ) {
        delete_user_meta( $user_id, 'ryvr_ip_restrictions' );
        
        // Log the change
        Logger::log( 'auth', 'IP restrictions removed for user #' . $user_id );
        
        return true;
    }
    
    /**
     * Check if MFA is enabled for a user
     * 
     * @since 1.0.0
     * @param int $user_id User ID
     * @return bool True if MFA is enabled
     */
    public function is_mfa_enabled( $user_id ) {
        return (bool) get_user_meta( $user_id, 'ryvr_mfa_enabled', true );
    }
    
    /**
     * Enable MFA for a user
     * 
     * @since 1.0.0
     * @param int    $user_id    User ID
     * @param string $secret_key TOTP secret key
     * @return bool True on success
     */
    public function enable_mfa( $user_id, $secret_key ) {
        // Store the secret key
        update_user_meta( $user_id, 'ryvr_mfa_secret', $secret_key );
        update_user_meta( $user_id, 'ryvr_mfa_enabled', true );
        
        // Log the change
        Logger::log( 'auth', 'MFA enabled for user #' . $user_id );
        
        return true;
    }
    
    /**
     * Disable MFA for a user
     * 
     * @since 1.0.0
     * @param int $user_id User ID
     * @return bool True on success
     */
    public function disable_mfa( $user_id ) {
        // Remove MFA data
        delete_user_meta( $user_id, 'ryvr_mfa_secret' );
        delete_user_meta( $user_id, 'ryvr_mfa_enabled' );
        
        // Log the change
        Logger::log( 'auth', 'MFA disabled for user #' . $user_id );
        
        return true;
    }
    
    /**
     * Verify MFA code
     * 
     * @since 1.0.0
     * @param int    $user_id User ID
     * @param string $code    TOTP code
     * @return bool True if code is valid
     */
    public function verify_mfa_code( $user_id, $code ) {
        // Get the secret key
        $secret = get_user_meta( $user_id, 'ryvr_mfa_secret', true );
        
        if ( empty( $secret ) ) {
            return false;
        }
        
        // Use TOTP library to verify code
        require_once RYVR_PLUGIN_DIR . 'includes/lib/TOTP.php';
        
        $totp = new \TOTP\TOTP( $secret );
        $result = $totp->verify( $code );
        
        if ( $result ) {
            // Log successful verification
            Logger::log( 'auth', 'MFA verified for user #' . $user_id );
        } else {
            // Log failed verification
            Logger::log( 'auth', 'MFA verification failed for user #' . $user_id );
        }
        
        return $result;
    }
    
    /**
     * Complete MFA verification and create full session
     * 
     * @since 1.0.0
     * @param int $user_id User ID
     * @return void
     */
    private function complete_mfa_verification( $user_id ) {
        // Clear MFA session flags
        unset( $_SESSION['ryvr_mfa_required'] );
        unset( $_SESSION['ryvr_mfa_user_id'] );
        
        // Create full session
        $this->create_session( $user_id );
        
        // Log the successful MFA login
        Logger::log( 'auth', 'User #' . $user_id . ' completed MFA login' );
    }
    
    /**
     * Generate a new MFA secret key
     * 
     * @since 1.0.0
     * @return string Secret key
     */
    public function generate_mfa_secret() {
        require_once RYVR_PLUGIN_DIR . 'includes/lib/TOTP.php';
        
        $totp = new \TOTP\TOTP();
        return $totp->generateSecret();
    }
    
    /**
     * Generate a QR code URL for MFA setup
     * 
     * @since 1.0.0
     * @param int    $user_id User ID
     * @param string $secret  Secret key
     * @return string QR code URL
     */
    public function generate_mfa_qr_url( $user_id, $secret ) {
        $user = get_user_by( 'id', $user_id );
        $site_name = get_bloginfo( 'name' );
        
        require_once RYVR_PLUGIN_DIR . 'includes/lib/TOTP.php';
        
        $totp = new \TOTP\TOTP( $secret );
        return $totp->getQRCodeUrl( $user->user_email, $site_name );
    }
    
    /**
     * AJAX handler to verify MFA code
     * 
     * @since 1.0.0
     * @return void
     */
    public function ajax_verify_mfa() {
        // Verify nonce
        check_ajax_referer( 'ryvr_mfa_nonce', 'nonce' );
        
        // Check if MFA verification is in progress
        if ( !isset( $_SESSION['ryvr_mfa_required'] ) || !isset( $_SESSION['ryvr_mfa_user_id'] ) ) {
            wp_send_json_error( array( 'message' => 'Invalid MFA session' ) );
            return;
        }
        
        $user_id = $_SESSION['ryvr_mfa_user_id'];
        $code = isset( $_POST['code'] ) ? sanitize_text_field( $_POST['code'] ) : '';
        
        if ( empty( $code ) ) {
            wp_send_json_error( array( 'message' => 'Code is required' ) );
            return;
        }
        
        // Verify the code
        if ( $this->verify_mfa_code( $user_id, $code ) ) {
            // Complete MFA verification
            $this->complete_mfa_verification( $user_id );
            
            wp_send_json_success( array( 
                'message' => 'MFA verified successfully',
                'redirect' => admin_url()
            ) );
        } else {
            wp_send_json_error( array( 'message' => 'Invalid verification code' ) );
        }
    }
    
    /**
     * AJAX handler to setup MFA
     * 
     * @since 1.0.0
     * @return void
     */
    public function ajax_setup_mfa() {
        // Verify nonce
        check_ajax_referer( 'ryvr_mfa_nonce', 'nonce' );
        
        // Check user permission
        if ( !current_user_can( 'edit_user', get_current_user_id() ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied' ) );
            return;
        }
        
        $user_id = get_current_user_id();
        
        // Generate a new secret
        $secret = $this->generate_mfa_secret();
        
        // Generate QR code URL
        $qr_url = $this->generate_mfa_qr_url( $user_id, $secret );
        
        // Save secret temporarily
        update_user_meta( $user_id, 'ryvr_mfa_setup_secret', $secret );
        
        wp_send_json_success( array(
            'message' => 'MFA setup initiated',
            'secret' => $secret,
            'qr_url' => $qr_url
        ) );
    }
    
    /**
     * AJAX handler to verify and enable MFA
     * 
     * @since 1.0.0
     * @return void
     */
    public function ajax_confirm_mfa_setup() {
        // Verify nonce
        check_ajax_referer( 'ryvr_mfa_nonce', 'nonce' );
        
        // Check user permission
        if ( !current_user_can( 'edit_user', get_current_user_id() ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied' ) );
            return;
        }
        
        $user_id = get_current_user_id();
        $code = isset( $_POST['code'] ) ? sanitize_text_field( $_POST['code'] ) : '';
        
        if ( empty( $code ) ) {
            wp_send_json_error( array( 'message' => 'Code is required' ) );
            return;
        }
        
        // Get the temporary secret
        $secret = get_user_meta( $user_id, 'ryvr_mfa_setup_secret', true );
        
        if ( empty( $secret ) ) {
            wp_send_json_error( array( 'message' => 'MFA setup session expired' ) );
            return;
        }
        
        // Verify the code
        require_once RYVR_PLUGIN_DIR . 'includes/lib/TOTP.php';
        
        $totp = new \TOTP\TOTP( $secret );
        if ( $totp->verify( $code ) ) {
            // Enable MFA with the verified secret
            $this->enable_mfa( $user_id, $secret );
            
            // Remove temporary secret
            delete_user_meta( $user_id, 'ryvr_mfa_setup_secret' );
            
            wp_send_json_success( array( 'message' => 'MFA enabled successfully' ) );
        } else {
            wp_send_json_error( array( 'message' => 'Invalid verification code' ) );
        }
    }
    
    /**
     * AJAX handler to disable MFA
     * 
     * @since 1.0.0
     * @return void
     */
    public function ajax_disable_mfa() {
        // Verify nonce
        check_ajax_referer( 'ryvr_mfa_nonce', 'nonce' );
        
        // Check user permission
        if ( !current_user_can( 'edit_user', get_current_user_id() ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied' ) );
            return;
        }
        
        $user_id = get_current_user_id();
        $password = isset( $_POST['password'] ) ? $_POST['password'] : '';
        
        if ( empty( $password ) ) {
            wp_send_json_error( array( 'message' => 'Password is required' ) );
            return;
        }
        
        // Verify password
        $user = get_user_by( 'id', $user_id );
        if ( !$user || !wp_check_password( $password, $user->data->user_pass, $user->ID ) ) {
            wp_send_json_error( array( 'message' => 'Invalid password' ) );
            return;
        }
        
        // Disable MFA
        $this->disable_mfa( $user_id );
        
        wp_send_json_success( array( 'message' => 'MFA disabled successfully' ) );
    }
} 