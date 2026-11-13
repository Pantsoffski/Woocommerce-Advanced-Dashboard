<?php
/** Read-only API smoke plus reversible preference/upgrade simulation in the sandbox. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! defined( 'ADWC_INTEGRATION_SANDBOX' ) || ! ADWC_INTEGRATION_SANDBOX || 'adwc_stage2_isolated' !== DB_NAME || 'local' !== wp_get_environment_type() ) {
	throw new RuntimeException( 'Wrong sandbox.' );
}
wp_set_current_user( get_user_by( 'login', 'adwc_admin' )->ID );
foreach ( array( '7', '30', '90' ) as $preset ) {
	$data = ADWC_Analytics::read( ADWC_Date_Range::resolve( $preset ) );
	if ( 'ready' !== $data['state'] || count( $data['rows'] ) !== (int) $preset ) { throw new RuntimeException( 'Report failed.' ); }
}
$prices = array( 'woocommerce_currency_pos', 'woocommerce_price_decimal_sep', 'woocommerce_price_thousand_sep', 'woocommerce_price_num_decimals' );
$saved = array();
foreach ( $prices as $key ) { $saved[ $key ] = get_option( $key ); }
try {
	foreach ( array( array( 'left', '.', ',' ), array( 'right_space', ',', ' ' ) ) as $format ) {
		update_option( $prices[0], $format[0] ); update_option( $prices[1], $format[1] ); update_option( $prices[2], $format[2] ); update_option( $prices[3], 2 );
		$data = ADWC_Analytics::read( ADWC_Date_Range::resolve( '30' ) );
		$expected = html_entity_decode( wp_strip_all_tags( wc_price( $data['totals']['net_revenue'], array( 'currency' => get_woocommerce_currency() ) ) ), ENT_QUOTES, 'UTF-8' );
		if ( $expected !== $data['totals']['formatted']['net_revenue'] ) { throw new RuntimeException( 'Price format mismatch.' ); }
	}
} finally { foreach ( $saved as $key => $value ) { update_option( $key, $value ); } }
if ( ( $args[0] ?? '' ) === 'upgrade' ) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
	$id = get_current_user_id();
	$options = array( 'meta-box-order_dashboard', 'metaboxhidden_dashboard', 'closedpostboxes_dashboard', 'screen_layout_dashboard' );
	$previous = array();
	foreach ( $options as $key ) { $previous[ $key ] = get_user_option( $key ); }
	$fixture = array( array( 'normal' => 'dashboard_activity,advanced-dashboard-admin-widget' ), array( 'advanced-dashboard-admin-widget' ), array( 'advanced-dashboard-admin-widget' ), 2 );
	$plugin = 'advanced-dashboard-for-woocommerce/pp-woocommerce-advanced-dashboard.php';
	try {
		foreach ( $options as $index => $key ) { update_user_option( $id, $key, $fixture[ $index ], false ); }
		$baseline = array(); foreach ( $options as $key ) { $baseline[ $key ] = get_user_option( $key ); }
		deactivate_plugins( $plugin );
		$result = activate_plugin( $plugin );
		if ( is_wp_error( $result ) ) { throw new RuntimeException( $result->get_error_message() ); }
		foreach ( $options as $index => $key ) { if ( get_user_option( $key ) !== $baseline[ $key ] ) { throw new RuntimeException( 'Preference changed: ' . $key ); } }
		WP_CLI::log( 'PASS upgrade identity/preferences: order, hidden, closed and layout retained; historical widget ID unchanged.' );
	} finally {
		foreach ( $previous as $key => $value ) { if ( false === $value ) { delete_user_option( $id, $key, false ); } else { update_user_option( $id, $key, $value, false ); } }
	}
}
WP_CLI::success( 'Real API/price smoke: PHP ' . PHP_VERSION . '; WP ' . get_bloginfo( 'version' ) . '; WC ' . WC_VERSION . '; locale ' . get_locale() . '; queue ' . count( ADWC_Operations::read()['rows'] ) );
