<?php
/**
 * Plugin Name: BigSeller Region Map for WooCommerce
 * Description: Maps WooCommerce order REST API country and state values to BigSeller region labels using a database-backed lookup table. Bundles a Malaysia seed dataset.
 * Version: 1.0.0
 * Author: Shaiful Borhan
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: bigseller-region-map-for-woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BIGSELLER_REGION_MAP_PLUGIN_VERSION', '1.0.0' );
define( 'BIGSELLER_REGION_MAP_PLUGIN_FILE', __FILE__ );
define( 'BIGSELLER_REGION_MAP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

require_once BIGSELLER_REGION_MAP_PLUGIN_DIR . 'includes/settings.php';
require_once BIGSELLER_REGION_MAP_PLUGIN_DIR . 'includes/runtime.php';
require_once BIGSELLER_REGION_MAP_PLUGIN_DIR . 'includes/activation.php';

register_activation_hook( BIGSELLER_REGION_MAP_PLUGIN_FILE, 'bigseller_region_map_activate' );
