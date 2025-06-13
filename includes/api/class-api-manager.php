<?php
/**
 * The API Manager class.
 *
 * Manages API connections and services.
 *
 * @package    Ryvr
 * @subpackage Ryvr/API
 */

namespace Ryvr\API;

/**
 * The API Manager class.
 *
 * This class manages all API connections and services.
 *
 * @package    Ryvr
 * @subpackage Ryvr/API
 */
class API_Manager {

    /**
     * API services.
     *
     * @var array
     */
    private $services = [];

    /**
     * Initialize the API manager.
     *
     * @return void
     */
    public function init() {
        // Register API services.
        $this->register_services();
    }

    /**
     * Register API services.
     *
     * @return void
     */
    private function register_services() {
        // Only include files if they're not already loaded
        if (!class_exists('\\Ryvr\\API\\Services\\DataForSEO_Service')) {
            require_once RYVR_INCLUDES_DIR . 'api/services/class-dataforseo-service.php';
        }
        
        if (!class_exists('\\Ryvr\\API\\Services\\OpenAI_Service')) {
            require_once RYVR_INCLUDES_DIR . 'api/services/class-openai-service.php';
        }
        
        // Register services - use a try/catch to handle potential errors
        try {
            $this->register_service('dataforseo', new Services\DataForSEO_Service());
            $this->register_service('openai', new Services\OpenAI_Service());
            
            // Initialize services.
            foreach ($this->services as $service) {
                $service->init();
            }
            
            // Allow other plugins to register API services.
            do_action('ryvr_register_api_services', $this);
        } catch (\Exception $e) {
            // Log any errors for debugging
            error_log('Ryvr ERROR: Failed to register API services: ' . $e->getMessage());
        }
    }

    /**
     * Register an API service.
     *
     * @param string $name Service name.
     * @param object $service Service instance.
     * @return bool Whether the registration was successful.
     */
    public function register_service( $name, $service ) {
        if ( isset( $this->services[ $name ] ) ) {
            return false;
        }
        
        $this->services[ $name ] = $service;
        
        return true;
    }

    /**
     * Get an API service.
     *
     * @param string $name Service name.
     * @param int    $client_id Optional. Client ID to use for service.
     * @return object|null Service instance or null if not found.
     */
    public function get_service( $name, $client_id = 0 ) {
        if ( ! isset( $this->services[ $name ] ) ) {
            return null;
        }
        
        $service = $this->services[ $name ];
        
        // If a client ID is provided, apply client-specific configuration
        if ( $client_id > 0 ) {
            $service->set_client_id( $client_id );
        }
        
        return $service;
    }

    /**
     * Get all API services.
     *
     * @return array All services.
     */
    public function get_services() {
        return $this->services;
    }
} 