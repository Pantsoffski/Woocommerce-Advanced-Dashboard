<?php
/** Run: php tests/run.php. API doubles only; no database, network or store writes. */
if ( 'cli' !== PHP_SAPI ) { http_response_code( 404 ); exit; }
require __DIR__ . '/stubs.php';
require dirname( __DIR__ ) . '/pp-woocommerce-advanced-dashboard.php';
set_error_handler( static function ( $severity, $message, $file, $line ) { throw new ErrorException( $message, 0, $severity, $file, $line ); } );
$passed = 0;
function check( $condition, $name ) {
	global $passed;
	if ( ! $condition ) { fwrite( STDERR, "FAIL: $name\n" ); exit( 1 ); }
	++$passed;
	echo "PASS: $name\n";
}
function request( $params = array() ) {
	$request = new WP_REST_Request();
	$request->set_header( 'X-WP-Nonce', 'valid-nonce' );
	$request->set_query_params( $params );
	return $request;
}
function fixture( $date = '2026-11-13', $sales = 125.5, $orders = 2, $items = 3 ) {
	$values = array( 'net_revenue' => $sales, 'orders_count' => $orders, 'num_items_sold' => $items );
	return array( 'totals' => $values, 'intervals' => array( array( 'date_start' => $date . 'T00:00:00', 'subtotals' => $values ) ) );
}
adwc_reset();
foreach ( $GLOBALS['hooks']['plugins_loaded'] as $callback ) { $callback(); }
ob_start();
ADWC_Plugin::register_widget(); ADWC_Plugin::render(); ADWC_Plugin::enqueue( 'index.php' );
$output = ob_get_clean();
check( '' === $output && ! $GLOBALS['widgets'] && ! $GLOBALS['assets'], 'No WooCommerce: no widget, output, data or assets' );
$result = ADWC_Plugin::summary( request() );
check( is_wp_error( $result ) && 'adwc_woocommerce_missing' === $result->get_error_code(), 'No WooCommerce: authenticated request returns 503' );
check( 'adwc_woocommerce_missing' === ADWC_Plugin::operations( request() )->get_error_code(), 'No WooCommerce: operational endpoint returns 503 without order access' );
ob_start(); ADWC_Plugin::dependency_notice(); $notice = ob_get_clean();
check( false !== strpos( $notice, 'needs an active WooCommerce' ), 'No WooCommerce: dashboard notice for administrator' );
// Conditional declaration keeps WooCommerce absent during the preceding bootstrap checks.
if ( ! class_exists( 'WooCommerce' ) ) { class WooCommerce {} }
adwc_reset();
ADWC_Plugin::register_widget();
check( array( 'advanced-dashboard-admin-widget' ) === $GLOBALS['widgets'], 'Existing widget ID preserved' );
ADWC_Plugin::enqueue( 'edit.php' ); ADWC_Plugin::enqueue( 'plugins.php' );
check( ! $GLOBALS['assets'] && ! $GLOBALS['order_queries'], 'Other admin screens load no assets or data' );
ADWC_Plugin::enqueue( 'index.php' );
check( 2 === count( $GLOBALS['assets'] ) && ! $GLOBALS['order_queries'], 'Dashboard enqueues local assets without fetching data' );
check( ! isset( $GLOBALS['hooks']['admin_head'] ), 'No admin_head hook' );
$GLOBALS['caps'] = array(); $GLOBALS['assets'] = array();
ADWC_Plugin::enqueue( 'index.php' ); ob_start(); ADWC_Plugin::render(); $html = ob_get_clean();
$result = ADWC_Plugin::summary( request() );
check( '' === $html && ! $GLOBALS['assets'] && is_wp_error( $result ) && ! $GLOBALS['order_queries'] && ! $GLOBALS['analytics_queries'], 'Subscriber/no capability: no render, enqueue or data access' );
adwc_reset();
check( 'adwc_nonce' === ADWC_Plugin::summary( new WP_REST_Request() )->get_error_code() && ! $GLOBALS['order_queries'], 'Missing nonce fails before any data access' );
$bad_nonce = request(); $bad_nonce->set_header( 'X-WP-Nonce', '<script>' );
check( 'adwc_nonce' === ADWC_Plugin::summary( $bad_nonce )->get_error_code(), 'Invalid nonce rejected' );
$GLOBALS['network'] = true; ADWC_Plugin::enqueue( 'index.php' );
check( ! $GLOBALS['assets'], 'Network dashboard excluded' );
adwc_reset();
$bad_dates = array( array( '2026-02-30', '2026-03-01' ), array( '2026-11-14', '2026-11-14' ), array( '2026-11-13', '2026-11-01' ), array( '2026-01-01', '2026-11-01' ), array( '<script>', '2026-11-01' ), array( array(), '2026-11-01' ), array( '', '' ), array( '2026-1-01', '2026-11-01' ) );
foreach ( $bad_dates as $pair ) check( is_wp_error( ADWC_Date_Range::resolve( 'custom', $pair[0], $pair[1] ) ), 'Reject malformed/future/reversed/oversized dates: ' . json_encode( $pair ) );
check( is_wp_error( ADWC_Date_Range::resolve( array() ) ) && is_wp_error( ADWC_Date_Range::resolve( 'all' ) ), 'Reject invalid range types and presets' );
$range = ADWC_Date_Range::resolve( '7', '', '', new DateTimeImmutable( '2026-01-03', wp_timezone() ) );
check( '2025-12-28' === $range['start']->format( 'Y-m-d' ) && '2026-01-03 23:59:59' === $range['end']->format( 'Y-m-d H:i:s' ), 'Seven days includes today and crosses years' );
$range = ADWC_Date_Range::resolve( '30', '', '', new DateTimeImmutable( '2026-03-02', wp_timezone() ) );
check( '2026-02-01' === $range['start']->format( 'Y-m-d' ), 'Thirty days crosses month boundary' );
$range = ADWC_Date_Range::resolve( 'custom', '2024-02-28', '2024-03-01' );
check( 3 === count( ADWC_Analytics::normalize( $GLOBALS['payload'], $range ) ), 'Leap day filled without orders' );
foreach ( array( array( '2026-03-29', 23 ), array( '2026-10-25', 25 ) ) as $case ) {
	$range = ADWC_Date_Range::resolve( 'custom', $case[0], $case[0] );
	check( $case[1] * 3600 === $range['end']->getTimestamp() - $range['start']->getTimestamp() + 1, 'Store timezone DST day: ' . $case[0] );
}
$GLOBALS['timezone'] = 'Pacific/Kiritimati';
$range = ADWC_Date_Range::resolve( '7', '', '', new DateTimeImmutable( '2026-01-01 12:30:00', new DateTimeZone( 'UTC' ) ) );
check( '2026-01-02' === $range['end']->format( 'Y-m-d' ), 'Store timezone overrides server/UTC calendar date' );
adwc_reset();
$range = ADWC_Date_Range::resolve( '7' );
$rows = ADWC_Analytics::normalize( fixture(), $range );
check( 7 === count( $rows ) && 0 === $rows[0]['net_revenue'] && 125.5 === $rows[6]['net_revenue'], 'Normalize full daily series with missing days' );
$object_payload = fixture();
$object_payload['intervals'][0]['subtotals'] = (object) $object_payload['intervals'][0]['subtotals'];
$object_payload['intervals'][0] = (object) $object_payload['intervals'][0];
check( ! is_wp_error( ADWC_Analytics::normalize( $object_payload, $range ) ), 'Real in-process Analytics stdClass buckets and subtotals normalize correctly' );
$broken = fixture(); $broken['totals']['orders_count'] = 5;
check( is_wp_error( ADWC_Analytics::normalize( $broken, $range ) ), 'Inconsistent aggregate totals rejected' );
$broken = fixture(); $broken['intervals'][] = $broken['intervals'][0];
check( is_wp_error( ADWC_Analytics::normalize( $broken, $range ) ), 'Duplicate days rejected' );
$broken = fixture(); $broken['intervals'][0]['subtotals']['net_revenue'] = INF;
check( is_wp_error( ADWC_Analytics::normalize( $broken, $range ) ), 'Non-finite amounts rejected' );
check( is_wp_error( ADWC_Analytics::normalize( fixture( '2025-01-01' ), $range ) ) && is_wp_error( ADWC_Analytics::normalize( array(), $range ) ), 'Out-of-range and incomplete reports rejected' );
$response = ADWC_Plugin::summary( request( array( 'range' => '7' ) ) ); $data = $response->get_data();
check( 'ready' === $data['state'] && $data['empty'] && 7 === count( $data['rows'] ), 'Verified empty store produces zero-filled charts' );
check( 'no-store, private' === $response->headers['Cache-Control'], 'Financial responses prohibit shared caching' );
$query = $GLOBALS['analytics_queries'][0];
check( 'date_created' === $query['date_type'] && '2026-11-13T23:59:59' === $query['before'] && 'day' === $query['interval'], 'Analytics query uses explicit creation dates and inclusive local days' );
check( array( 'processing' => 4, 'on_hold' => 2 ) === $data['counts'], 'Operational counts use modern WC count API' );
adwc_reset(); $GLOBALS['payload'] = fixture(); $GLOBALS['source_count'] = $GLOBALS['currency_count'] = 2;
$data = ADWC_Plugin::summary( request() )->get_data();
check( 'ready' === $data['state'] && false === $data['empty'] && '125,50 zł' === $data['totals']['formatted']['net_revenue'], 'Single-currency sales and localized currency formatting' );
check( ! isset( $data['order_id'], $data['customer'], $data['nonce'] ), 'Report contains aggregates, no order/customer details or nonce' );
$GLOBALS['caps']['edit_shop_orders'] = false;
check( null === ADWC_Plugin::summary( request() )->get_data()['counts'], 'Report-only role cannot access operational counts' );
adwc_reset(); $GLOBALS['import_stats'] = array( 'is_importing' => true );
check( 'unavailable' === ADWC_Plugin::summary( request() )->get_data()['state'] && ! $GLOBALS['analytics_queries'], 'Import in progress blocks charts before aggregate query' );
adwc_reset(); $GLOBALS['import_stats'] = array( 'orders' => array( 'total' => 5, 'imported' => 2 ) );
check( 'unavailable' === ADWC_Plugin::summary( request() )->get_data()['state'], 'Incomplete historical import blocks charts' );
adwc_reset(); $GLOBALS['import_stats'] = array( 'imported_from' => '2026-11-10 00:00:00' );
check( 'unavailable' === ADWC_Plugin::summary( request() )->get_data()['state'], 'Import coverage does not include requested start' );
adwc_reset(); $GLOBALS['failed_imports'] = array( 'overflow' => 1 );
check( 'unavailable' === ADWC_Plugin::summary( request() )->get_data()['state'], 'Failed imports block charts' );
adwc_reset(); $GLOBALS['scheduled'] = true;
check( 'unavailable' === ADWC_Plugin::summary( request() )->get_data()['state'], 'Scheduled import without checkpoint blocks charts' );
$GLOBALS['options']['woocommerce_admin_scheduler_last_processed_order_modified_date'] = '2026-11-13 08:00:00'; $GLOBALS['changed'] = array( 10 );
check( 'unavailable' === ADWC_Plugin::summary( request() )->get_data()['state'], 'Changes since scheduled sync checkpoint block stale charts' );
$GLOBALS['changed'] = array(); $GLOBALS['same_second'] = array( 10 ); $GLOBALS['options']['woocommerce_admin_scheduler_last_processed_order_id'] = 10;
check( 'ready' === ADWC_Plugin::summary( request() )->get_data()['state'], 'Already imported checkpoint-second order does not block current data' );
$GLOBALS['same_second'] = array( 11 );
check( 'unavailable' === ADWC_Plugin::summary( request() )->get_data()['state'], 'Higher ID within checkpoint second is recognized as pending' );
adwc_reset(); $GLOBALS['routes'] = array();
check( 'unavailable' === ADWC_Plugin::summary( request() )->get_data()['state'], 'Missing Analytics route blocks charts' );
adwc_reset(); $GLOBALS['analytics_status'] = 403;
check( 'unavailable' === ADWC_Plugin::summary( request() )->get_data()['state'], 'WooCommerce report permission errors handled' );
adwc_reset(); $GLOBALS['source_count'] = $GLOBALS['currency_count'] = 1;
check( 'unavailable' === ADWC_Plugin::summary( request() )->get_data()['state'], 'Unimported orders never become false zero sales' );
adwc_reset(); $GLOBALS['source_count'] = 2; $GLOBALS['currency_count'] = 1;
$data = ADWC_Plugin::summary( request() )->get_data();
check( 'unavailable' === $data['state'] && ! $data['rows'] && ! isset( $data['totals'] ), 'Mixed/unknown currencies never return summed financial figures' );
adwc_reset(); $GLOBALS['refunds'] = array( new ADWC_Test_Order( 22, 11 ) ); $GLOBALS['parents'][11] = new ADWC_Test_Order( 11, 0, 'EUR' );
check( 'unavailable' === ADWC_Plugin::summary( request() )->get_data()['state'], 'Refund from older foreign-currency parent is detected' );
$GLOBALS['parents'][11] = new ADWC_Test_Order( 11 );
check( 'unavailable' === ADWC_Plugin::summary( request() )->get_data()['state'], 'Unimported refund never becomes false zero sales' );
$GLOBALS['lookup_refunds'] = array( 22 ); $GLOBALS['payload'] = fixture( '2026-11-13', -20, 0, -1 );
$data = ADWC_Plugin::summary( request() )->get_data();
check( 'ready' === $data['state'] && ! $data['empty'] && -20.0 === $data['totals']['net_revenue'], 'Refund-only range preserves negative money and quantity' );
adwc_reset(); $GLOBALS['refunds'] = array_fill( 0, 101, new ADWC_Test_Order( 22, 11 ) );
check( 'unavailable' === ADWC_Plugin::summary( request() )->get_data()['state'], 'Refund verification is capped without a historical scan' );
adwc_reset(); $GLOBALS['db_error'] = 'SECRET SQL /server/path';
$result = ADWC_Plugin::summary( request() );
check( is_wp_error( $result ) && false === strpos( $result->get_error_message(), 'SECRET' ) && ! $GLOBALS['wpdb']->suppressed, 'Database errors are redacted and error suppression restored' );
adwc_reset(); $GLOBALS['rest_url'] = 'https://example.test/" onmouseover="<script>alert(1)</script>';
ob_start(); ADWC_Plugin::render(); $html = ob_get_clean();
check( false === strpos( $html, '<script>' ) && false === strpos( $html, '" onmouseover=' ) && false === strpos( $html, '125,50' ) && ! $GLOBALS['order_queries'], 'Widget escapes attributes and embeds no financial payload' );
adwc_reset();
check( 72 === ADWC_Operations::preferences()['age_hours'], 'Operational threshold defaults to 72 hours' );
foreach ( array( 0, -1, 8761, 72.5, '1e2', array(), '<script>', '0' ) as $invalid ) {
	check( is_wp_error( ADWC_Operations::save( $invalid, array( 'wc-processing' ) ) ), 'Reject invalid operational threshold: ' . json_encode( $invalid ) );
}
check( is_wp_error( ADWC_Operations::save( 72, array( 'wc-unknown' ) ) ) && is_wp_error( ADWC_Operations::save( 72, array() ) ), 'Reject unregistered or empty queue statuses' );
check( is_wp_error( ADWC_Operations::save( 72, array_fill( 0, 21, 'wc-processing' ) ) ), 'Queue status selection is bounded' );
$saved = ADWC_Operations::save( '120', array( 'wc-processing', 'wc-pending' ) );
check( 120 === $saved['age_hours'] && $saved === ADWC_Operations::preferences(), 'Preferences save and reload for the current user' );
$GLOBALS['user_id'] = 2;
check( 72 === ADWC_Operations::preferences()['age_hours'], 'Operational preferences are isolated per user' );
$GLOBALS['queue_orders'] = array( new ADWC_Test_Order( 80 ), new ADWC_Test_Order( 81 ) );
$GLOBALS['denied_orders'] = array( 81 );
$operations = ADWC_Operations::read();
check( 1 === count( $operations['rows'] ) && '80' === $operations['rows'][0]['number'], 'Order-level permissions filter queue records' );
check( isset( $operations['counts']['processing'], $operations['counts']['on_hold'], $operations['counts']['pending'] ), 'All three operational counts are returned' );
$query = $GLOBALS['order_queries'][0];
check( 10 === $query['limit'] && false === $query['paginate'] && 'date' === $query['orderby'] && 'ASC' === $query['order'], 'Queue query has a hard 10-record limit, creation-date ordering and no total scan' );
$GLOBALS['queue_orders'] = array_map( static function ( $id ) { return new ADWC_Test_Order( $id ); }, range( 100, 124 ) );
check( 10 === count( ADWC_Operations::read()['rows'] ), 'Response remains capped even if another extension expands query results' );
$GLOBALS['caps'] = array( 'view_woocommerce_reports' => true ); $GLOBALS['order_queries'] = array();
check( 'adwc_forbidden' === ADWC_Plugin::operations( request() )->get_error_code() && ! $GLOBALS['order_queries'], 'Report-only capability cannot read order queue' );
check( 'adwc_forbidden' === ADWC_Plugin::save_preferences( request() )->get_error_code(), 'Report-only capability cannot write operational preferences' );
adwc_reset();
check( 'adwc_nonce' === ADWC_Plugin::save_preferences( new WP_REST_Request() )->get_error_code() && ! $GLOBALS['user_options'], 'Preference save rejects missing nonce before any write' );
echo "\n$passed isolated checks passed (API doubles; real integration is a separate suite).\n";
