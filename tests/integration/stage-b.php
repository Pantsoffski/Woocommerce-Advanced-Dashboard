<?php
/** Real order queue/preferences tests; writes only to the marked local sandbox. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! defined( 'ADWC_INTEGRATION_SANDBOX' ) || ! ADWC_INTEGRATION_SANDBOX || 'adwc_stage2_isolated' !== DB_NAME || 'local' !== wp_get_environment_type() || ! get_option( 'adwc_stage_a_passed' ) ) {
	throw new RuntimeException( 'Refusing stage B tests outside a validated local sandbox.' );
}
$GLOBALS['ib_checks'] = 0;
function ib_check( $condition, $label ) {
	if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $label ); }
	++$GLOBALS['ib_checks']; WP_CLI::log( 'PASS: ' . $label );
}
function ib_request( $method = 'GET', $params = array(), $nonce = true ) {
	$request = new WP_REST_Request( $method, '/advanced-dashboard-for-woocommerce/v1/operations' . ( 'POST' === $method ? '/preferences' : '' ) );
	if ( $nonce ) { $request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) ); }
	$request->set_query_params( $params );
	return rest_do_request( $request );
}
$admin = get_user_by( 'login', 'adwc_admin' );
$manager = get_user_by( 'login', 'adwc_manager' );
wp_set_current_user( $admin->ID );
delete_user_option( $admin->ID, ADWC_Operations::USER_OPTION, false );
$before_queries = count( $GLOBALS['wpdb']->queries );
$response = ib_request(); $data = $response->get_data();
ib_check( ! $response->is_error(), 'Operational endpoint succeeds against real order store' );
ib_check( 72 === $data['preferences']['age_hours'], 'Default threshold is 72 hours' );
ib_check( count( $data['rows'] ) <= 10, 'Queue never returns more than ten orders' );
ib_check( isset( $data['counts']['processing'], $data['counts']['on_hold'], $data['counts']['pending'] ), 'Processing/on-hold/pending counters returned' );
foreach ( array( 'processing' => 'processing', 'on_hold' => 'on-hold', 'pending' => 'pending' ) as $key => $status ) {
	ib_check( $data['counts'][ $key ] === wc_orders_count( $status ), 'Counter matches authoritative WC API: ' . $status );
}
$times = array_map( static function ( $row ) { return strtotime( $row['created_iso'] ); }, $data['rows'] );
$sorted = $times; sort( $sorted );
ib_check( $times === $sorted && ! array_filter( $data['rows'], static function ( $row ) { return $row['age_seconds'] <= 72 * HOUR_IN_SECONDS; } ), 'Oldest first, strictly older than threshold, age measured in elapsed seconds' );
$queries = array_slice( $GLOBALS['wpdb']->queries, $before_queries );
$source_queries = array_filter( $queries, static function ( $query ) {
	return preg_match( '/SELECT.*(?:wc_orders|posts).*LIMIT\s+(?:0\s*,\s*)?10\b/is', $query[0] );
} );
ib_check( (bool) $source_queries, 'Database queue selection has LIMIT 10 (no paginated history enumeration)' );
WP_CLI::log( 'PROFILE cold request: ' . count( $queries ) . ' SQL statements; ' . round( array_sum( array_column( $queries, 1 ) ) * 1000, 2 ) . ' ms SQL.' );

if ( 'empty' === ( $args[0] ?? '' ) ) {
	ib_check( ! $data['rows'] && 0 === array_sum( $data['counts'] ), 'Real zero-order queue and counters are empty' );
	WP_CLI::success( $GLOBALS['ib_checks'] . ' real operational checks passed.' ); return;
}
ib_check( 10 === count( $data['rows'] ), 'Dozens of synthetic orders yield a ten-record bounded queue' );
ib_check( ! str_contains( wp_json_encode( $data ), 'synthetic@example.test' ) && ! isset( $data['rows'][0]['billing'] ), 'Operational payload contains no customer contact fields' );
$is_hpos = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
ib_check( str_contains( $data['rows'][0]['edit_url'], $is_hpos ? 'page=wc-orders' : 'post.php' ), 'Edit URL matches authoritative HPOS/CPT mode' );

$valid = ib_request( 'POST', array( 'age_hours' => 120, 'statuses' => array( 'wc-needs-review' ) ) );
ib_check( ! $valid->is_error() && 120 === get_user_option( ADWC_Operations::USER_OPTION )['age_hours'], 'Real user preference is saved and read back' );
$custom = ib_request()->get_data();
ib_check( $custom['rows'] && ! array_filter( $custom['rows'], static function ( $row ) { return 'Needs review <synthetic>' !== $row['status']; } ), 'Registered custom status is selectable and preserved as plain text' );
$newer_threshold = ib_request( 'POST', array( 'age_hours' => 8760, 'statuses' => array( 'wc-processing', 'wc-on-hold', 'wc-pending' ) ) );
ib_check( ! $newer_threshold->is_error() && ! ib_request()->get_data()['rows'], 'Changing threshold produces a genuine empty matching queue' );
foreach ( array( 0, -1, 8761, '1e2', 72.5, array(), '<script>' ) as $invalid ) {
	ib_check( 400 === ib_request( 'POST', array( 'age_hours' => $invalid, 'statuses' => array( 'wc-processing' ) ) )->get_status(), 'Reject invalid real preference value: ' . wp_json_encode( $invalid ) );
}
ib_check( 400 === ib_request( 'POST', array( 'age_hours' => 72, 'statuses' => array( 'wc-unregistered' ) ) )->get_status(), 'Unknown status cannot be persisted' );
$saved_before = get_user_option( ADWC_Operations::USER_OPTION );
ib_check( 403 === ib_request( 'POST', array( 'age_hours' => 1, 'statuses' => array( 'wc-processing' ) ), false )->get_status() && $saved_before === get_user_option( ADWC_Operations::USER_OPTION ), 'Missing nonce never changes preferences' );

delete_user_option( $manager->ID, ADWC_Operations::USER_OPTION, false );
ib_request( 'POST', array( 'age_hours' => 120, 'statuses' => array( 'wc-needs-review' ), 'user_id' => $manager->ID ) );
ib_check( false === get_user_option( ADWC_Operations::USER_OPTION, $manager->ID ), 'Submitted target user ID cannot write another user preference' );
wp_set_current_user( $manager->ID );
$manager_data = ib_request()->get_data();
ib_check( 72 === $manager_data['preferences']['age_hours'], 'Manager has independent site-specific preferences' );
ib_check( ! ib_request( 'POST', array( 'age_hours' => 48, 'statuses' => array( 'wc-pending' ) ) )->is_error(), 'Shop manager can save their own preferences' );
wp_set_current_user( $admin->ID );
ib_check( 120 === ADWC_Operations::preferences()['age_hours'], 'Manager preference save does not affect administrator' );

$role = get_role( 'adwc_report_only' );
if ( ! $role ) { add_role( 'adwc_report_only', 'Synthetic report-only role', array( 'read' => true, 'view_woocommerce_reports' => true ) ); }
$report_user = get_user_by( 'login', 'adwc_report_only' );
if ( ! $report_user ) { $id = wp_insert_user( array( 'user_login' => 'adwc_report_only', 'user_pass' => 'adwc-local-review-only', 'user_email' => 'report-only@example.test', 'role' => 'adwc_report_only' ) ); $report_user = get_user_by( 'id', $id ); }
foreach ( array( get_user_by( 'login', 'adwc_subscriber' ), $report_user ) as $user ) {
	wp_set_current_user( $user->ID );
	ib_check( 403 === ib_request()->get_status() && 403 === ib_request( 'POST', array( 'age_hours' => 72, 'statuses' => array( 'wc-pending' ) ) )->get_status(), 'Read/write denied for role without edit_shop_orders: ' . $user->user_login );
}
wp_set_current_user( $admin->ID );
ib_request( 'POST', array( 'age_hours' => 72, 'statuses' => array( 'wc-processing', 'wc-on-hold', 'wc-pending', 'wc-needs-review' ) ) );
$queue = ADWC_Operations::read();
$number = $queue['rows'][0]['number'];
$order = wc_get_orders( array( 'type' => 'shop_order', 'status' => $queue['preferences']['statuses'], 'orderby' => 'date', 'order' => 'ASC', 'limit' => 1 ) )[0];
$created_before = $order->get_date_created()->getTimestamp();
$order->set_date_modified( time() ); $order->save();
$modified_before = $order->get_date_modified()->getTimestamp();
$after = ADWC_Operations::read();
ib_check( $after['rows'][0]['number'] === $number && $after['rows'][0]['age_seconds'] >= time() - $created_before - 2, 'Recent modification does not reset age from order creation' );
$read_again = wc_get_order( $order->get_id() );
ib_check( $read_again->get_date_modified()->getTimestamp() === $modified_before && $read_again->get_date_created()->getTimestamp() === $created_before, 'Queue reads and preference writes do not modify orders' );
update_option( 'adwc_synthetic_xss_number', $order->get_id(), false );
$malicious = ADWC_Operations::read();
ib_check( str_contains( $malicious['rows'][0]['number'], '<img' ), 'Synthetic hostile order number reaches JSON as a text value for browser escaping test' );
// Restore current Analytics after the deliberate synthetic order edit.
\Automattic\WooCommerce\Internal\Admin\Schedulers\OrdersScheduler::import( $order->get_id() );
\Automattic\WooCommerce\Admin\ReportsSync::clear_queued_actions();
WP_CLI::success( $GLOBALS['ib_checks'] . ' real operational checks passed.' );
