<?php
/**
 * Plugin Name: Advanced Dashboard for WooCommerce
 * Plugin URI: http://ordin.pl/
 * Description: Readable sales, order and product quantity charts on the WordPress dashboard.
 * Author: Piotr Pesta
 * Version: 1.0.0
 * Author URI: http://ordin.pl/
 * License: GPLv2 or later
 * Text Domain: advanced-dashboard-for-woocommerce
 * Requires at least: 7.0.8
 * Requires PHP: 8.2.35
 * Requires Plugins: woocommerce
 * WC requires at least: 11.2.2
 * WC tested up to: 11.3.0
 *
 * @package AdvancedDashboardForWooCommerce
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

require_once __DIR__ . '/includes/class-adwc-date-range.php';
require_once __DIR__ . '/includes/class-adwc-analytics.php';
require_once __DIR__ . '/includes/class-adwc-operations.php';
require_once __DIR__ . '/includes/class-adwc-plugin.php';

add_action(
	'plugins_loaded',
	static function () {
		ADWC_Plugin::init( __FILE__ );
	}
);
