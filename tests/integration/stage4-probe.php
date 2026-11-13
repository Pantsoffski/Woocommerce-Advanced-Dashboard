<?php
/** Test-only observability; mounted only in stage 4 sandboxes. */
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'ADWC_INTEGRATION_SANDBOX' ) || ! ADWC_INTEGRATION_SANDBOX || 'local' !== wp_get_environment_type() ) { return; }
add_action( 'woocommerce_init', static function () {
	$features = \Automattic\WooCommerce\Utilities\FeaturesUtil::get_compatible_features_for_plugin( 'advanced-dashboard-for-woocommerce/pp-woocommerce-advanced-dashboard.php' );
	$GLOBALS['adwc_stage4_hpos_declared'] = in_array( 'custom_order_tables', $features['compatible'], true );
}, 99 );
add_action( 'send_headers', static function () {
	header( 'X-ADWC-HPOS-Declared: ' . ( ! empty( $GLOBALS['adwc_stage4_hpos_declared'] ) ? 'yes' : 'no' ) );
} );
