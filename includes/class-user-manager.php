<?php

namespace Ryvr\Platform;

/**
 * Class responsible for managing users in the Ryvr AI Platform.
 *
 * @since      1.0.0
 * @package    Ryvr\Platform
 */
class User_Manager {

    /**
     * Status constants
     */
    const STATUS_ACTIVE = 'active';
    const STATUS_SUSPENDED = 'suspended';
    const STATUS_PENDING = 'pending';
    const STATUS_INACTIVE = 'inactive';
    
    /**
     * The database manager instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      Database_Manager    $db_manager    The database manager instance.
     */
    private $db_manager;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param    Database_Manager    $db_manager    The database manager instance.
     */
    public function __construct( $db_manager ) {
        $this->db_manager = $db_manager;
    }
    
    /**
     * Set the user's account status
     *
     * @since    1.0.0
     * @param    int       $user_id    The WP user ID
     * @param    string    $status     The status to set (use class constants)
     * @return   boolean   True on success, false on failure
     */
    public function set_account_status( $user_id, $status ) {
        // Validate status
        if ( !in_array( $status, [ self::STATUS_ACTIVE, self::STATUS_SUSPENDED, self::STATUS_PENDING, self::STATUS_INACTIVE ] ) ) {
            return false;
        }
        
        // Update user meta with status
        update_user_meta( $user_id, 'ryvr_account_status', $status );
        
        // Log the status change
        Logger::log( 'user', 'Account status changed to ' . $status . ' for user #' . $user_id );
        
        // Fire action for status change
        do_action( 'ryvr_account_status_changed', $user_id, $status );
        
        return true;
    }
    
    /**
     * Get the user's account status
     *
     * @since    1.0.0
     * @param    int       $user_id    The WP user ID
     * @return   string    The account status
     */
    public function get_account_status( $user_id ) {
        $status = get_user_meta( $user_id, 'ryvr_account_status', true );
        return !empty( $status ) ? $status : self::STATUS_ACTIVE; // Default to active
    }
    
    /**
     * Check if a user account is active
     *
     * @since    1.0.0
     * @param    int       $user_id    The WP user ID
     * @return   boolean   True if active, false otherwise
     */
    public function is_account_active( $user_id ) {
        return $this->get_account_status( $user_id ) === self::STATUS_ACTIVE;
    }
    
    /**
     * Suspend a user account
     *
     * @since    1.0.0
     * @param    int       $user_id    The WP user ID
     * @param    string    $reason     Optional reason for suspension
     * @return   boolean   True on success, false on failure
     */
    public function suspend_account( $user_id, $reason = '' ) {
        if ( !empty( $reason ) ) {
            update_user_meta( $user_id, 'ryvr_suspension_reason', $reason );
        }
        return $this->set_account_status( $user_id, self::STATUS_SUSPENDED );
    }
    
    /**
     * Activate a user account
     *
     * @since    1.0.0
     * @param    int       $user_id    The WP user ID
     * @return   boolean   True on success, false on failure
     */
    public function activate_account( $user_id ) {
        delete_user_meta( $user_id, 'ryvr_suspension_reason' );
        return $this->set_account_status( $user_id, self::STATUS_ACTIVE );
    }
    
    // Other existing user management methods...
} 