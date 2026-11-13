<?php
/** Final compatibility assertions, no order or preference writes. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! defined( 'ADWC_INTEGRATION_SANDBOX' ) || ! ADWC_INTEGRATION_SANDBOX ) { throw new RuntimeException( 'Sandbox only.' ); }
$plugin = 'advanced-dashboard-for-woocommerce/pp-woocommerce-advanced-dashboard.php';
$features = \Automattic\WooCommerce\Utilities\FeaturesUtil::get_compatible_features_for_plugin( $plugin );
if ( ! in_array( 'custom_order_tables', $features['compatible'], true ) || ! did_action( 'before_woocommerce_init' ) || is_admin() ) { throw new RuntimeException( 'HPOS declaration absent outside wp-admin.' ); }
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$headers = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin, false, false );
foreach ( array( 'Version' => '1.0.0', 'RequiresWP' => '7.0.8', 'RequiresPHP' => '8.2.35', 'RequiresPlugins' => 'woocommerce', 'TextDomain' => 'advanced-dashboard-for-woocommerce' ) as $key => $value ) {
	if ( $headers[ $key ] !== $value ) { throw new RuntimeException( 'Header mismatch: ' . $key ); }
}
WP_Plugin_Dependencies::initialize();
if ( ! in_array( 'woocommerce', WP_Plugin_Dependencies::get_dependencies( $plugin ), true ) ) { throw new RuntimeException( 'Core dependency registration missing.' ); }
if ( is_wp_error( validate_plugin_requirements( $plugin ) ) ) { throw new RuntimeException( 'Core rejects tested requirements.' ); }
$without_woocommerce = static function ( $plugins ) { return array_values( array_diff( $plugins, array( 'woocommerce/woocommerce.php' ) ) ); };
add_filter( 'option_active_plugins', $without_woocommerce );
try {
	$missing = validate_plugin_requirements( $plugin );
	if ( ! is_wp_error( $missing ) || 'plugin_missing_dependencies' !== $missing->get_error_code() ) { throw new RuntimeException( 'Core did not enforce the WooCommerce dependency.' ); }
} finally { remove_filter( 'option_active_plugins', $without_woocommerce ); }
WP_CLI::log( 'PASS core Requires Plugins validation accepts active WooCommerce and rejects missing dependency without changing the database.' );
wp_set_current_user( get_user_by( 'login', 'adwc_admin' )->ID );
$data = ADWC_Analytics::read( ADWC_Date_Range::resolve( '7' ) );
if ( 'ready' !== $data['state'] ) { throw new RuntimeException( 'Final real aggregate read failed.' ); }
WP_CLI::success( 'Headers/dependency/HPOS declaration outside admin PASS; WP ' . get_bloginfo( 'version' ) . '; WC ' . WC_VERSION . '; PHP ' . PHP_VERSION . '; storage=' . ( \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ? 'HPOS' : 'CPT' ) );
