<?php
/**
 * API Cache Manager
 *
 * Manages caching for API responses to reduce API calls and improve performance.
 *
 * @package    Ryvr
 * @subpackage Ryvr/API
 */

namespace Ryvr\API;

/**
 * API Cache class.
 *
 * Handles caching of API responses with configurable TTL, cache groups,
 * and invalidation mechanisms.
 *
 * @package    Ryvr
 * @subpackage Ryvr/API
 */
class API_Cache {

    /**
     * Default cache time to live in seconds (1 hour)
     *
     * @var int
     */
    private $default_ttl = 3600;

    /**
     * Cache prefix for all keys
     *
     * @var string
     */
    private $cache_prefix = 'ryvr_api_cache_';

    /**
     * Initialize the class.
     *
     * @param int $default_ttl Optional. Default cache TTL in seconds.
     */
    public function __construct( $default_ttl = null ) {
        if ( $default_ttl !== null ) {
            $this->default_ttl = (int) $default_ttl;
        }
        
        // Maybe use custom TTL from settings
        $saved_ttl = get_option( 'ryvr_api_cache_ttl', null );
        if ( $saved_ttl !== null ) {
            $this->default_ttl = (int) $saved_ttl;
        }
        
        // Register cache cleanup on deactivation
        register_deactivation_hook( RYVR_PLUGIN_FILE, array( $this, 'clear_all_cache' ) );
    }

    /**
     * Get a cached API response.
     *
     * @param string $service   API service identifier (e.g., 'openai', 'dataforseo').
     * @param string $endpoint  API endpoint or method called.
     * @param array  $params    Request parameters used to generate the cache key.
     * @param int    $ttl       Optional. Time to live in seconds. Default is class default_ttl.
     * @return mixed|false      The cached data or false if not in cache or expired.
     */
    public function get( $service, $endpoint, $params, $ttl = null ) {
        $cache_key = $this->generate_cache_key( $service, $endpoint, $params );
        $cached_data = get_transient( $cache_key );
        
        if ( $cached_data === false ) {
            return false;
        }
        
        // Check if the cache has custom expiry metadata
        if ( isset( $cached_data['_cache_expires'] ) ) {
            if ( time() > $cached_data['_cache_expires'] ) {
                $this->delete( $service, $endpoint, $params );
                return false;
            }
            return $cached_data['data'];
        }
        
        return $cached_data;
    }

    /**
     * Set an API response in cache.
     *
     * @param string $service   API service identifier.
     * @param string $endpoint  API endpoint or method called.
     * @param array  $params    Request parameters used to generate the cache key.
     * @param mixed  $data      Data to cache.
     * @param int    $ttl       Optional. Time to live in seconds. Default is class default_ttl.
     * @return bool             True on success, false on failure.
     */
    public function set( $service, $endpoint, $params, $data, $ttl = null ) {
        $cache_key = $this->generate_cache_key( $service, $endpoint, $params );
        $ttl = $ttl ?? $this->default_ttl;

        // Store cache with metadata for more precise expiration checking
        $cache_data = array(
            'data' => $data,
            '_cache_expires' => time() + $ttl,
            '_cache_created' => time(),
            '_cache_service' => $service,
            '_cache_endpoint' => $endpoint
        );
        
        $result = set_transient( $cache_key, $cache_data, $ttl );
        
        // Track the cache key for group-based invalidation
        $this->add_key_to_group( $service, $cache_key );
        $this->add_key_to_group( "{$service}_{$endpoint}", $cache_key );
        
        return $result;
    }

    /**
     * Delete a specific cached API response.
     *
     * @param string $service   API service identifier.
     * @param string $endpoint  API endpoint or method called.
     * @param array  $params    Request parameters used to generate the cache key.
     * @return bool             True on success, false on failure.
     */
    public function delete( $service, $endpoint, $params ) {
        $cache_key = $this->generate_cache_key( $service, $endpoint, $params );
        return delete_transient( $cache_key );
    }

    /**
     * Clear all cached data for a specific service.
     *
     * @param string $service API service identifier.
     * @return int Number of cache entries cleared.
     */
    public function clear_service_cache( $service ) {
        return $this->clear_group_cache( $service );
    }

    /**
     * Clear all cached data for a specific endpoint of a service.
     *
     * @param string $service  API service identifier.
     * @param string $endpoint API endpoint or method called.
     * @return int Number of cache entries cleared.
     */
    public function clear_endpoint_cache( $service, $endpoint ) {
        return $this->clear_group_cache( "{$service}_{$endpoint}" );
    }

    /**
     * Clear all API cache.
     *
     * @return bool True on success, false on failure.
     */
    public function clear_all_cache() {
        // Get all cache groups
        $groups = $this->get_all_groups();
        $cleared_count = 0;
        
        // Clear each group
        foreach ( $groups as $group ) {
            $cleared_count += $this->clear_group_cache( $group );
        }
        
        // Clear group tracking
        delete_option( 'ryvr_api_cache_groups' );
        
        return $cleared_count;
    }

    /**
     * Set default TTL for cache entries.
     *
     * @param int $ttl Time to live in seconds.
     * @return void
     */
    public function set_default_ttl( $ttl ) {
        $this->default_ttl = (int) $ttl;
        update_option( 'ryvr_api_cache_ttl', $this->default_ttl );
    }

    /**
     * Generate a cache key based on service, endpoint, and parameters.
     *
     * @param string $service  API service identifier.
     * @param string $endpoint API endpoint or method called.
     * @param array  $params   Request parameters.
     * @return string The generated cache key.
     */
    private function generate_cache_key( $service, $endpoint, $params ) {
        // Sort params by key to ensure consistent cache keys regardless of parameter order
        if ( is_array( $params ) ) {
            ksort( $params );
        }
        
        // Serialize parameters and generate hash
        $params_hash = md5( serialize( $params ) );
        
        return $this->cache_prefix . $service . '_' . $endpoint . '_' . $params_hash;
    }

    /**
     * Add a cache key to a tracking group for later invalidation.
     *
     * @param string $group Group identifier.
     * @param string $key   Cache key to add to the group.
     * @return bool True on success, false on failure.
     */
    private function add_key_to_group( $group, $key ) {
        $groups = get_option( 'ryvr_api_cache_groups', array() );
        
        if ( !isset( $groups[$group] ) ) {
            $groups[$group] = array();
        }
        
        // Only add the key if it doesn't already exist
        if ( !in_array( $key, $groups[$group] ) ) {
            $groups[$group][] = $key;
            update_option( 'ryvr_api_cache_groups', $groups );
        }
        
        return true;
    }

    /**
     * Get all cache groups.
     *
     * @return array List of cache group identifiers.
     */
    private function get_all_groups() {
        $groups = get_option( 'ryvr_api_cache_groups', array() );
        return array_keys( $groups );
    }

    /**
     * Clear all cached data for a specific group.
     *
     * @param string $group Group identifier.
     * @return int Number of cache entries cleared.
     */
    private function clear_group_cache( $group ) {
        $groups = get_option( 'ryvr_api_cache_groups', array() );
        
        if ( !isset( $groups[$group] ) || empty( $groups[$group] ) ) {
            return 0;
        }
        
        $cleared_count = 0;
        
        // Delete each cache key in the group
        foreach ( $groups[$group] as $key ) {
            if ( delete_transient( $key ) ) {
                $cleared_count++;
            }
        }
        
        // Clear group tracking
        unset( $groups[$group] );
        update_option( 'ryvr_api_cache_groups', $groups );
        
        return $cleared_count;
    }

    /**
     * Get cache statistics.
     *
     * @return array Cache statistics.
     */
    public function get_cache_stats() {
        $groups = get_option( 'ryvr_api_cache_groups', array() );
        $stats = array(
            'total_cached_items' => 0,
            'by_service' => array(),
            'by_endpoint' => array(),
        );
        
        foreach ( $groups as $group => $keys ) {
            $count = count( $keys );
            
            // Check if this is a service group
            if ( strpos( $group, '_' ) === false ) {
                $stats['by_service'][$group] = $count;
            } 
            // Otherwise it's a service_endpoint group
            else {
                $stats['by_endpoint'][$group] = $count;
            }
            
            $stats['total_cached_items'] += $count;
        }
        
        return $stats;
    }
} 