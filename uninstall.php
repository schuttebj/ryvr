<?php
/**
 * Uninstall Ryvr AI Platform
 *
 * @package Ryvr
 */

// Exit if accessed directly.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Load Uninstaller class.
require_once dirname( __FILE__ ) . '/includes/core/class-uninstaller.php';

// Run uninstaller.
\Ryvr\Core\Uninstaller::uninstall(); 