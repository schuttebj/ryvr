<?php
/**
 * The Loader class.
 *
 * This class is responsible for maintaining and registering all hooks that power the plugin.
 *
 * @package    Ryvr
 * @subpackage Ryvr/Core
 */

namespace Ryvr\Core;

/**
 * The Loader class.
 *
 * This class is responsible for maintaining and registering all hooks that power the plugin.
 * It keeps track of actions and filters and provides methods to register them with WordPress.
 *
 * @package    Ryvr
 * @subpackage Ryvr/Core
 */
class Loader {

    /**
     * The array of actions registered with WordPress.
     *
     * @var array $actions The actions registered with WordPress to fire when the plugin loads.
     */
    protected $actions = [];

    /**
     * The array of filters registered with WordPress.
     *
     * @var array $filters The filters registered with WordPress to apply when the plugin loads.
     */
    protected $filters = [];

    /**
     * Initialize the loader.
     *
     * @return void
     */
    public function init() {
        // Nothing specific to initialize here.
    }

    /**
     * Add a new action to the collection to be registered with WordPress.
     *
     * @param string $hook          The name of the WordPress action.
     * @param object $component     The object that contains the action callback.
     * @param string $callback      The name of the callback function.
     * @param int    $priority      Optional. The priority at which the function should be fired. Default is 10.
     * @param int    $accepted_args Optional. The number of arguments the function accepts. Default is 1.
     * @return void
     */
    public function add_action( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
        $this->actions = $this->add( $this->actions, $hook, $component, $callback, $priority, $accepted_args );
    }

    /**
     * Add a new filter to the collection to be registered with WordPress.
     *
     * @param string $hook          The name of the WordPress filter.
     * @param object $component     The object that contains the filter callback.
     * @param string $callback      The name of the callback function.
     * @param int    $priority      Optional. The priority at which the function should be fired. Default is 10.
     * @param int    $accepted_args Optional. The number of arguments the function accepts. Default is 1.
     * @return void
     */
    public function add_filter( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
        $this->filters = $this->add( $this->filters, $hook, $component, $callback, $priority, $accepted_args );
    }

    /**
     * A utility function that is used to register the actions and hooks into a single collection.
     *
     * @param array  $hooks         The collection of hooks (actions or filters) to be registered.
     * @param string $hook          The name of the WordPress action or filter.
     * @param object $component     The object that contains the callback.
     * @param string $callback      The name of the callback function.
     * @param int    $priority      The priority at which the function should be fired.
     * @param int    $accepted_args The number of arguments the function accepts.
     * @return array The collection of hooks after the new one is registered.
     */
    private function add( $hooks, $hook, $component, $callback, $priority, $accepted_args ) {
        $hooks[] = [
            'hook'          => $hook,
            'component'     => $component,
            'callback'      => $callback,
            'priority'      => $priority,
            'accepted_args' => $accepted_args,
        ];

        return $hooks;
    }

    /**
     * Register all actions and filters with WordPress.
     *
     * @return void
     */
    public function run() {
        foreach ( $this->filters as $hook ) {
            add_filter(
                $hook['hook'],
                [ $hook['component'], $hook['callback'] ],
                $hook['priority'],
                $hook['accepted_args']
            );
        }

        foreach ( $this->actions as $hook ) {
            add_action(
                $hook['hook'],
                [ $hook['component'], $hook['callback'] ],
                $hook['priority'],
                $hook['accepted_args']
            );
        }
    }
} 