<?php
/** Render a deterministic widget for browser tests using the real view and data formatter. */
if ( 'cli' !== PHP_SAPI ) { http_response_code( 404 ); exit; }
require __DIR__ . '/stubs.php';
require dirname( __DIR__ ) . '/pp-woocommerce-advanced-dashboard.php';
adwc_reset();
if ( ! class_exists( 'WooCommerce' ) ) { class WooCommerce {} }
ADWC_Plugin::init( dirname( __DIR__ ) . '/pp-woocommerce-advanced-dashboard.php' );
$GLOBALS['rest_plain'] = true;
$values = array( array( 1340.5, 4, 7 ), array( 0, 0, 0 ), array( 2300, 6, 9 ), array( -300, 0, -1 ), array( 650, 2, 3 ), array( 1800, 3, 5 ), array( 900, 2, 4 ) );
$totals = array( 'net_revenue' => 0, 'orders_count' => 0, 'num_items_sold' => 0 );
$intervals = array();
foreach ( $values as $index => $amounts ) {
	$row = array_combine( array_keys( $totals ), $amounts );
	foreach ( $row as $key => $value ) { $totals[ $key ] += $value; }
	$intervals[] = array( 'date_start' => sprintf( '2026-11-%02dT00:00:00', $index + 7 ), 'subtotals' => $row );
}
$GLOBALS['payload'] = array( 'totals' => $totals, 'intervals' => $intervals );
$GLOBALS['source_count'] = $GLOBALS['currency_count'] = $totals['orders_count'];
$data = ADWC_Analytics::read( ADWC_Date_Range::resolve( '7' ) );
$data['counts'] = array( 'processing' => 4, 'on_hold' => 2 );
ob_start(); ADWC_Plugin::render(); $html = ob_get_clean();
echo json_encode( array( 'html' => $html, 'data' => $data, 'operations' => ADWC_Operations::read() ), JSON_THROW_ON_ERROR );
