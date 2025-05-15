<?php
/**
 * The I18n class.
 *
 * Defines internationalization functionality for the plugin.
 *
 * @package    Ryvr
 * @subpackage Ryvr/Core
 */

namespace Ryvr\Core;

/**
 * The I18n class.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @package    Ryvr
 * @subpackage Ryvr/Core
 */
class I18n {

    /**
     * Initialize the class.
     *
     * @return void
     */
    public function init() {
        add_action( 'plugins_loaded', array( $this, 'load_plugin_textdomain' ) );
    }

    /**
     * Load the plugin text domain for translation.
     *
     * @return void
     */
    public function load_plugin_textdomain() {
        load_plugin_textdomain(
            'ryvr-ai',
            false,
            dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
        );
    }
} 