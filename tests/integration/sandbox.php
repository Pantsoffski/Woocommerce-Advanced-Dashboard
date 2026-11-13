<?php
/** MU plugin for the explicitly isolated synthetic integration environment only. */
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'ADWC_INTEGRATION_SANDBOX' ) || ! ADWC_INTEGRATION_SANDBOX || 'adwc_stage2_isolated' !== DB_NAME || 'local' !== wp_get_environment_type() ) {
	return;
}
add_filter( 'pre_wp_mail', '__return_false' );
add_filter( 'woocommerce_allow_marketplace_suggestions', '__return_false' );
add_filter( 'woocommerce_tracker_send_event', '__return_false' );
add_action( 'init', static function () {
	register_post_status( 'wc-needs-review', array( 'label' => 'Needs review <synthetic>', 'public' => true, 'show_in_admin_status_list' => true, 'show_in_admin_all_list' => true ) );
} );
add_filter( 'wc_order_statuses', static function ( $statuses ) {
	$statuses['wc-needs-review'] = 'Needs review <synthetic>';
	return $statuses;
} );
add_filter( 'woocommerce_order_number', static function ( $number, $order ) {
	return (int) get_option( 'adwc_synthetic_xss_number' ) === $order->get_id() ? 'Synthetic <img src=x onerror=alert(1)> ' . $number : $number;
}, 10, 2 );
