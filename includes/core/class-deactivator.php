<?php
/**
 * The Deactivator class.
 *
 * Fired during plugin deactivation.
 *
 * @package    Ryvr
 * @subpackage Ryvr/Core
 */

namespace Ryvr\Core;

/**
 * The Deactivator class.
 *
 * This class handles all the tasks that need to be performed
 * when the plugin is deactivated.
 *
 * @package    Ryvr
 * @subpackage Ryvr/Core
 */
class Deactivator {

    /**
     * Plugin deactivation tasks.
     *
     * This method is called when the plugin is deactivated.
     * It cleans up temporary data and performs other deactivation tasks.
     *
     * @return void
     */
    public static function deactivate() {
        // Clear scheduled tasks.
        self::clear_scheduled_tasks();
        
        // Flush rewrite rules.
        flush_rewrite_rules();
    }

    /**
     * Clear scheduled tasks.
     *
     * Removes all scheduled tasks created by the plugin.
     *
     * @return void
     */
    private static function clear_scheduled_tasks() {
        // Clear all scheduled cron jobs with our prefix.
        $cron_jobs = _get_cron_array();
        
        if ( empty( $cron_jobs ) ) {
            return;
        }
        
        foreach ( $cron_jobs as $timestamp => $cron ) {
            foreach ( $cron as $hook => $events ) {
                if ( strpos( $hook, 'ryvr_' ) === 0 ) {
                    wp_unschedule_hook( $hook );
                }
            }
        }
    }
} 