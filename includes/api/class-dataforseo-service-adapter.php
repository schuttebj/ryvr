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

// Make sure the service class is loaded first
require_once RYVR_INCLUDES_DIR . 'api/services/class-dataforseo-service.php';

if (!class_exists('\\Ryvr\\API\\DataForSEO_Service')) {
    /**
     * The DataForSEO_Service adapter class.
     *
     * This class acts as a compatibility layer for code expecting
     * the DataForSEO_Service class to be in the Ryvr\API namespace.
     *
     * @package    Ryvr
     * @subpackage Ryvr/API
     */
    class DataForSEO_Service extends Services\DataForSEO_Service {
        // This class inherits all functionality from the actual service
    }
} 