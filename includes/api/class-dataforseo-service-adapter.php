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
} 