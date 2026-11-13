<?php
/** Real WP/WC integration. Run only through wp eval-file in the supplied sandbox. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! defined( 'ADWC_INTEGRATION_SANDBOX' ) || ! ADWC_INTEGRATION_SANDBOX || 'adwc_stage2_isolated' !== DB_NAME || 'local' !== wp_get_environment_type() ) {
	throw new RuntimeException( 'Refusing integration writes outside the ADWC local sandbox.' );
}
use Automattic\WooCommerce\Internal\Admin\Schedulers\OrdersScheduler;
use Automattic\WooCommerce\Admin\ReportsSync;
use Automattic\WooCommerce\Admin\API\Reports\Cache as ReportsCache;
use Automattic\WooCommerce\Utilities\OrderUtil;

$GLOBALS['ia_checks'] = 0;
function ia_check( $condition, $label ) {
	if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $label ); }
	++$GLOBALS['ia_checks'];
	WP_CLI::log( 'PASS: ' . $label );
}
function ia_summary( $params = array() ) {
	$request = new WP_REST_Request( 'GET', '/advanced-dashboard-for-woocommerce/v1/summary' );
	$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
	$request->set_query_params( $params );
	$response = rest_do_request( $request );
	if ( $response->is_error() ) {
		// Diagnostic detail is CLI-only in the guarded sandbox, never returned by the plugin.
		ADWC_Analytics::read( ADWC_Date_Range::resolve( $params['range'] ?? 'month', $params['from'] ?? '', $params['to'] ?? '' ) );
		throw new RuntimeException( 'Endpoint error: ' . wp_json_encode( $response->get_data() ) );
	}
	return $response->get_data();
}
function ia_sync() {
	foreach ( get_option( 'adwc_synthetic_records', array() ) as $row ) {
		if ( wc_get_order( $row['id'] ) ) { OrdersScheduler::import( $row['id'] ); }
	}
	ReportsSync::clear_queued_actions();
	ReportsCache::invalidate();
}
function ia_reset() {
	foreach ( get_option( 'adwc_synthetic_records', array() ) as $row ) {
		$order = wc_get_order( $row['id'] );
		if ( $order ) {
			if ( 'yes' !== $order->get_meta( '_adwc_synthetic' ) ) { throw new RuntimeException( 'Unmarked order in synthetic fixture.' ); }
			$order->delete( true );
		}
	}
	update_option( 'adwc_synthetic_records', array(), false );
	ReportsSync::clear_queued_actions();
	ReportsCache::invalidate();
}
function ia_record( $order, $net, $items, $is_refund = false ) {
	$order->update_meta_data( '_adwc_synthetic', 'yes' );
	$order->save();
	$records = get_option( 'adwc_synthetic_records', array() );
	$records[] = array( 'id' => $order->get_id(), 'date' => $order->get_date_created()->date( 'Y-m-d' ), 'net' => $net, 'items' => $items, 'orders' => $is_refund ? 0 : 1, 'status' => $order->get_status() );
	update_option( 'adwc_synthetic_records', $records, false );
	return $order;
}
function ia_order( $date, $status, $quantity = 1, $currency = 'PLN', $free = false ) {
	$product = wc_get_product( get_option( 'adwc_synthetic_product' ) );
	$order = new WC_Order();
	$order->set_created_via( 'adwc-local-integration' );
	$order->set_currency( $currency );
	$order->set_billing_email( 'synthetic@example.test' );
	$order->set_date_created( $date->getTimestamp() );
	$order->set_status( $status );
	$order->set_date_paid( $date->modify( '+1 day' )->getTimestamp() );
	$order->add_product( $product, $quantity, array( 'subtotal' => $quantity * 10, 'total' => $free ? 0 : $quantity * 10 - 2 ) );
	if ( ! $free ) {
		$fee = new WC_Order_Item_Fee(); $fee->set_name( 'Synthetic fee' ); $fee->set_total( 1 ); $order->add_item( $fee );
		$shipping = new WC_Order_Item_Shipping(); $shipping->set_method_title( 'Synthetic shipping' ); $shipping->set_total( 3 ); $order->add_item( $shipping );
	}
	$order->calculate_totals( false );
	$order->save();
	return ia_record( $order, $free ? 0 : $quantity * 10 - 1, $quantity );
}
function ia_compare( $params, $label ) {
	$data = ia_summary( $params );
	ia_check( 'ready' === $data['state'], "$label ready: " . ( $data['message'] ?? '' ) );
	$expected = array();
	foreach ( $data['rows'] as $row ) { $expected[ $row['date'] ] = array( 0, 0, 0 ); }
	foreach ( get_option( 'adwc_synthetic_records', array() ) as $row ) {
		$order = wc_get_order( $row['id'] );
		$status = $order instanceof WC_Order_Refund ? wc_get_order( $order->get_parent_id() )->get_status() : $order->get_status();
		if ( ! isset( $expected[ $row['date'] ] ) || in_array( $status, array( 'pending', 'failed', 'cancelled' ), true ) ) { continue; }
		$expected[ $row['date'] ][0] += $row['net'];
		$expected[ $row['date'] ][1] += $row['orders'];
		$expected[ $row['date'] ][2] += $row['items'];
	}
	foreach ( $data['rows'] as $row ) {
		$want = $expected[ $row['date'] ];
		ia_check( abs( $row['net_revenue'] - $want[0] ) < 0.001 && $row['orders_count'] === $want[1] && abs( $row['num_items_sold'] - $want[2] ) < 0.001, "$label exact daily metrics " . $row['date'] );
	}
	return $data;
}

wp_set_current_user( get_user_by( 'login', 'adwc_admin' )->ID );
update_option( 'timezone_string', 'Europe/Warsaw' );
update_option( 'woocommerce_currency', 'PLN' );
update_option( 'woocommerce_calc_taxes', 'yes' );
update_option( 'woocommerce_date_type', 'date_paid' ); // The plugin MUST override this.
update_option( 'woocommerce_analytics_enabled', 'yes' );
update_option( 'woocommerce_analytics_scheduled_import', 'no' );
update_option( 'woocommerce_excluded_report_order_statuses', array( 'pending', 'failed', 'cancelled' ) );
delete_option( 'woocommerce_admin_import_stats' );
delete_option( 'woocommerce_admin_analytics_failed_order_imports' );
update_option( 'woocommerce_task_list_hidden', 'yes' );
update_option( 'woocommerce_onboarding_profile', array( 'completed' => true ) );

$command = $args[0] ?? 'verify';
if ( 'reset' === $command ) { ia_reset(); WP_CLI::success( 'Removed only marked synthetic fixture orders.' ); return; }
ia_check( class_exists( 'ADWC_Plugin' ) && ADWC_Plugin::available(), 'Plugin and WooCommerce active without fatal error' );
WP_CLI::log( 'VERSIONS: WordPress ' . get_bloginfo( 'version' ) . '; WooCommerce ' . WC_VERSION . '; PHP ' . PHP_VERSION . '; store=' . ( OrderUtil::custom_orders_table_usage_is_enabled() ? 'HPOS' : 'CPT' ) );

if ( 'empty' === $command ) {
	ia_check( 0 === wc_orders_count( 'processing' ), 'Isolated store starts without processing orders' );
	ReportsSync::clear_queued_actions();
	$data = ia_summary( array( 'range' => '7' ) );
	ia_check( 'ready' === $data['state'] && true === $data['empty'] && 7 === count( $data['rows'] ), 'Real empty Analytics response becomes a verified zero series: ' . wp_json_encode( $data ) );
	WP_CLI::success( $GLOBALS['ia_checks'] . ' real integration checks passed.' ); return;
}
if ( 'seed' === $command ) {
	ia_check( ! get_option( 'adwc_synthetic_records' ), 'No previous synthetic orders before seed' );
	$product = wc_get_product( get_option( 'adwc_synthetic_product' ) );
	if ( ! $product ) {
		$product = new WC_Product_Simple(); $product->set_name( 'ADWC synthetic product' ); $product->set_regular_price( 10 ); $product->set_status( 'publish' ); $product->save();
		update_option( 'adwc_synthetic_product', $product->get_id(), false );
	}
	$today = current_datetime()->setTime( 0, 0 );
	$statuses = array( 'processing', 'on-hold', 'completed', 'pending', 'cancelled', 'failed', 'needs-review' );
	for ( $i = 0; $i < 40; ++$i ) {
		ia_order( $today->modify( '-' . ( $i * 3 ) . ' days' )->setTime( 12, 0 ), $statuses[ $i % count( $statuses ) ], 1 + $i % 3 );
	}
	ia_order( $today->modify( '-1 day' ), 'processing', 1 );
	ia_order( $today->modify( '-1 day' )->setTime( 23, 59, 59 ), 'on-hold', 2 );
	ia_order( $today, 'completed', 3 );
	ia_order( $today, 'processing', 1, 'PLN', true );
	$parent = ia_order( $today->modify( '-10 days' ), 'completed', 1 );
	$item_id = array_key_first( $parent->get_items() );
	$refund = wc_create_refund( array( 'order_id' => $parent->get_id(), 'amount' => $parent->get_total(), 'line_items' => array( $item_id => array( 'qty' => 1, 'refund_total' => 8, 'refund_tax' => array() ) ), 'refund_payment' => false, 'restock_items' => false ) );
	if ( is_wp_error( $refund ) ) { throw new RuntimeException( $refund->get_error_message() ); }
	$refund->set_date_created( $today->getTimestamp() );
	// Full refund decomposition follows the installed Analytics rules: order net=9, shipping=3.
	ia_record( $refund, -9, -1, true );
	$partial_parent = ia_order( $today->modify( '-3 days' ), 'completed', 2 );
	$partial = wc_create_refund( array( 'order_id' => $partial_parent->get_id(), 'amount' => 5, 'refund_payment' => false, 'restock_items' => false ) );
	if ( is_wp_error( $partial ) ) { throw new RuntimeException( $partial->get_error_message() ); }
	$partial->set_date_created( $today->getTimestamp() ); ia_record( $partial, -5, 0, true );
	$taxed = ia_order( $today->modify( '-2 days' ), 'completed', 1 );
	$rate = WC_Tax::_insert_tax_rate( array( 'tax_rate_country' => 'PL', 'tax_rate' => '20.0000', 'tax_rate_name' => 'Synthetic VAT', 'tax_rate_priority' => 1, 'tax_rate_compound' => 0, 'tax_rate_shipping' => 0, 'tax_rate_order' => 0, 'tax_rate_class' => '' ) );
	$item = current( $taxed->get_items() );
	$item->set_taxes( array( 'total' => array( $rate => 1.6 ), 'subtotal' => array( $rate => 2 ) ) ); $item->save();
	$taxed->update_taxes(); $taxed->calculate_totals( false ); $taxed->save();
	ia_check( (float) $taxed->get_total_tax() > 0, 'Synthetic taxed order validates net-sales tax exclusion' );
	foreach ( array( '2026-03-28T23:59:59', '2026-03-29T00:00:00', '2026-03-29T23:59:59', '2026-10-25T00:00:00', '2025-12-31T23:59:59', '2026-01-01T00:00:00' ) as $time ) {
		ia_order( new DateTimeImmutable( $time, wp_timezone() ), 'completed', 1 );
	}
	foreach ( array( 'adwc_manager' => 'shop_manager', 'adwc_subscriber' => 'subscriber' ) as $login => $role ) {
		if ( ! get_user_by( 'login', $login ) ) { wp_insert_user( array( 'user_login' => $login, 'user_pass' => 'adwc-local-review-only', 'user_email' => $login . '@example.test', 'role' => $role ) ); }
	}
	ia_sync();
	ia_check( count( get_option( 'adwc_synthetic_records' ) ) >= 50, 'At least fifty marked synthetic order/refund records created' );
}

// Verify actual request parameters and compare against independent fixture expectations.
$seen = array();
$trace = static function ( $result, $server, $request ) use ( &$seen ) {
	if ( ADWC_Analytics::ROUTE === $request->get_route() ) { $seen[] = $request->get_param( 'date_type' ); }
	return $result;
};
add_filter( 'rest_pre_dispatch', $trace, 10, 3 );
foreach ( array( '7', '30', '90', 'month' ) as $preset ) { ia_compare( array( 'range' => $preset ), "Preset $preset" ); }
ia_compare( array( 'range' => 'custom', 'from' => '2026-03-28', 'to' => '2026-03-30' ), 'DST spring' );
ia_compare( array( 'range' => 'custom', 'from' => '2026-10-24', 'to' => '2026-10-26' ), 'DST autumn' );
ia_compare( array( 'range' => 'custom', 'from' => '2025-12-31', 'to' => '2026-01-01' ), 'Year boundary' );
ia_check( $seen && ! array_diff( $seen, array( 'date_created' ) ) && 'date_paid' === get_option( 'woocommerce_date_type' ), 'Explicit date_created overrides date_paid without changing merchant preference' );
remove_filter( 'rest_pre_dispatch', $trace, 10 );

update_option( 'woocommerce_analytics_enabled', 'no' );
ia_check( 'unavailable' === ia_summary()['state'], 'Disabled Analytics never becomes zero' );
update_option( 'woocommerce_analytics_enabled', 'yes' );
$first = get_option( 'adwc_synthetic_records' )[0]['id'];
OrdersScheduler::schedule_action( 'import', array( $first ) );
ia_check( 'unavailable' === ia_summary()['state'], 'Pending Analytics import never becomes zero' );
ia_sync();
ia_check( 'ready' === ia_summary()['state'], 'Completed import does not spuriously block charts' );
update_option( 'woocommerce_analytics_scheduled_import', 'yes' );
update_option( 'woocommerce_admin_scheduler_last_processed_order_modified_date', gmdate( 'Y-m-d H:i:s' ) );
update_option( 'woocommerce_admin_scheduler_last_processed_order_id', max( array_column( get_option( 'adwc_synthetic_records' ), 'id' ) ) );
ia_check( 'ready' === ia_summary()['state'], 'Current scheduled-import checkpoint does not spuriously block charts' );
update_option( 'woocommerce_admin_scheduler_last_processed_order_modified_date', gmdate( 'Y-m-d H:i:s', time() - 2 * DAY_IN_SECONDS ) );
ia_check( 'unavailable' === ia_summary()['state'], 'Real edits newer than scheduled checkpoint withhold stale charts' );
update_option( 'woocommerce_analytics_scheduled_import', 'no' );
ReportsSync::clear_queued_actions();

$foreign = ia_order( current_datetime()->modify( '-1 day' ), 'completed', 1, 'EUR' );
ia_sync();
ia_check( 'unavailable' === ia_summary()['state'], 'Real foreign-currency order withholds mixed totals' );
$foreign->set_status( 'cancelled' ); $foreign->save(); ia_sync();
ia_check( 'ready' === ia_summary()['state'], 'Excluded foreign-currency order does not block correct single-currency charts' );

foreach ( array( 'adwc_manager', 'adwc_subscriber' ) as $login ) {
	wp_set_current_user( get_user_by( 'login', $login )->ID );
	$request = new WP_REST_Request( 'GET', '/advanced-dashboard-for-woocommerce/v1/summary' ); $request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
	$response = rest_do_request( $request );
	ia_check( 'adwc_manager' === $login ? ! $response->is_error() : 403 === $response->get_status(), 'Real role boundary: ' . $login );
}
wp_set_current_user( get_user_by( 'login', 'adwc_admin' )->ID );
if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
	ia_check( 'no' === get_option( 'woocommerce_custom_orders_table_data_sync_enabled' ), 'HPOS posts synchronization disabled' );
	ia_check( 'shop_order' !== get_post_type( $first ), 'HPOS source order has no legacy shop_order post copy' );
}
update_option( 'adwc_stage_a_passed', true, false );
WP_CLI::success( $GLOBALS['ia_checks'] . ' real integration checks passed.' );
