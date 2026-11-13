<?php
/** Real original-file -> local candidate update, restricted to the new upgrade sandbox. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! defined( 'ADWC_INTEGRATION_SANDBOX' ) || ! ADWC_INTEGRATION_SANDBOX || 'local' !== wp_get_environment_type() || 'http://127.0.0.1:38976' !== get_option( 'siteurl' ) ) {
	throw new RuntimeException( 'Refusing upgrade test outside its isolated sandbox.' );
}
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$plugin = 'advanced-dashboard-for-woocommerce/pp-woocommerce-advanced-dashboard.php';
$admin = get_user_by( 'login', 'adwc_admin' );
wp_set_current_user( $admin->ID );
function u4_assert( $condition, $label ) { if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $label ); } WP_CLI::log( 'PASS: ' . $label ); }
function u4_snapshot( $ids ) {
	$result = array();
	foreach ( $ids as $id ) {
		$order = wc_get_order( $id );
		$result[ $id ] = array( $order->get_status(), $order->get_total(), $order->get_currency(), $order->get_date_created()->getTimestamp(), $order->get_date_modified()->getTimestamp(), $order->get_meta( '_adwc_upgrade_marker' ), $order->get_item_count() );
	}
	return $result;
}
$phase = $args[0] ?? 'verify';
if ( ! in_array( $phase, array( 'prepare', 'replace' ), true ) ) { u4_assert( '1.0.0' === get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin, false, false )['Version'], 'Installed replacement version is 1.0.0' ); }
if ( 'prepare' === $phase ) {
	u4_assert( class_exists( 'Advanced_Dashboard_Admin_Init' ) && ! class_exists( 'ADWC_Plugin' ) && is_plugin_active( $plugin ), 'Original 0.2.5 is active and its original PHP class is loaded' );
	require_once ABSPATH . 'wp-admin/includes/dashboard.php'; set_current_screen( 'dashboard' );
	Advanced_Dashboard_Admin_Init::wp_dashboard_setup();
	u4_assert( isset( $GLOBALS['wp_meta_boxes']['dashboard']['normal']['core']['advanced-dashboard-admin-widget'] ), 'Original code registers the historical widget ID' );
	update_option( 'timezone_string', 'Europe/Warsaw' ); update_option( 'woocommerce_currency', 'PLN' ); update_option( 'woocommerce_date_type', 'date_paid' );
	$ids = array();
	foreach ( array( 'processing', 'on-hold', 'completed' ) as $index => $status ) {
		$order = new WC_Order(); $order->set_status( $status ); $order->set_currency( 'PLN' ); $order->set_date_created( time() - ( $index + 4 ) * DAY_IN_SECONDS ); $order->set_total( 20 + $index ); $order->update_meta_data( '_adwc_upgrade_marker', 'original-install-synthetic' ); $order->save(); $ids[] = $order->get_id();
		\Automattic\WooCommerce\Internal\Admin\Schedulers\OrdersScheduler::import( $order->get_id() );
	}
	\Automattic\WooCommerce\Admin\ReportsSync::clear_queued_actions();
	$prefs = array( 'meta-box-order_dashboard' => array( 'normal' => 'dashboard_activity,advanced-dashboard-admin-widget,dashboard_right_now', 'side' => 'dashboard_quick_press' ), 'metaboxhidden_dashboard' => array( 'advanced-dashboard-admin-widget' ), 'closedpostboxes_dashboard' => array( 'advanced-dashboard-admin-widget' ), 'screen_layout_dashboard' => 2 );
	foreach ( $prefs as $key => $value ) { update_user_option( $admin->ID, $key, $value, false ); $prefs[ $key ] = get_user_option( $key ); }
	update_option( 'adwc_upgrade_legacy_sentinel', array( 'unrelated' => 'preserve me', 'nested' => array( 1, 2 ) ) );
	$snapshot = array( 'ids' => $ids, 'orders' => u4_snapshot( $ids ), 'prefs' => $prefs, 'sentinel' => get_option( 'adwc_upgrade_legacy_sentinel' ), 'timezone' => get_option( 'timezone_string' ), 'currency' => get_option( 'woocommerce_currency' ), 'date_type' => get_option( 'woocommerce_date_type' ) );
	update_option( 'adwc_upgrade_baseline', $snapshot, false );
	WP_CLI::success( 'Real original installation and its preferences/records prepared.' ); return;
}
if ( 'replace' === $phase ) {
	u4_assert( is_array( get_option( 'adwc_upgrade_baseline' ) ), 'Prepared original installation baseline exists before replacement' );
	u4_assert( class_exists( 'Advanced_Dashboard_Admin_Init' ), 'Update starts with original 0.2.5 code loaded' );
	require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
	$transient = get_site_transient( 'update_plugins' );
	$updates = is_object( $transient ) ? clone $transient : new stdClass();
	$updates->response[ $plugin ] = (object) array( 'slug' => 'advanced-dashboard-for-woocommerce', 'plugin' => $plugin, 'new_version' => '1.0.0', 'package' => '/adwc-local-rc.zip' );
	set_site_transient( 'update_plugins', $updates );
	try {
		$skin = new Automatic_Upgrader_Skin();
		$upgrader = new Plugin_Upgrader( $skin );
		$result = $upgrader->upgrade( $plugin );
		update_option( 'adwc_upgrade_execution', array( 'result' => $result, 'engine' => 'Plugin_Upgrader::upgrade', 'version' => '1.0.0' ), false );
		u4_assert( true === $result, 'WordPress Plugin_Upgrader replaced the actual original files using the local zip' );
	} finally { if ( false === $transient ) { delete_site_transient( 'update_plugins' ); } else { set_site_transient( 'update_plugins', $transient ); } }
	WP_CLI::success( 'Actual replacement finished; verify in a fresh PHP process.' ); return;
}
$baseline = get_option( 'adwc_upgrade_baseline' );
if ( 'post-files' === $phase ) {
	u4_assert( ! is_plugin_active( $plugin ), 'Core single-plugin updater performed its standard silent deactivation' );
	u4_assert( ! file_exists( WP_PLUGIN_DIR . '/advanced-dashboard-for-woocommerce/classes.php' ), 'Real updater removed obsolete original files' );
	foreach ( $baseline['prefs'] as $key => $value ) { u4_assert( get_user_option( $key ) === $value, 'Before reactivation preference retained: ' . $key ); }
	u4_assert( u4_snapshot( $baseline['ids'] ) === $baseline['orders'], 'Original synthetic orders survive the file replacement' );
	$activation = activate_plugin( $plugin );
	u4_assert( ! is_wp_error( $activation ) && is_plugin_active( $plugin ), 'Standard WordPress reactivation succeeds with candidate version 1.0.0' );
	WP_CLI::success( 'Post-update file/data state and standard reactivation verified.' ); return;
}
u4_assert( class_exists( 'ADWC_Plugin' ) && ! class_exists( 'Advanced_Dashboard_Admin_Init' ) && is_plugin_active( $plugin ), 'Fresh request loads replacement code after standard reactivation' );
u4_assert( ! file_exists( WP_PLUGIN_DIR . '/advanced-dashboard-for-woocommerce/classes.php' ), 'Obsolete original files were removed by the real updater' );
foreach ( $baseline['prefs'] as $key => $value ) { u4_assert( get_user_option( $key ) === $value, 'Upgrade retains ' . $key ); }
u4_assert( u4_snapshot( $baseline['ids'] ) === $baseline['orders'], 'Synthetic original order data remains byte-for-byte equivalent in the snapshot' );
u4_assert( get_option( 'adwc_upgrade_legacy_sentinel' ) === $baseline['sentinel'] && get_option( 'timezone_string' ) === $baseline['timezone'] && get_option( 'woocommerce_currency' ) === $baseline['currency'] && get_option( 'woocommerce_date_type' ) === $baseline['date_type'], 'Legacy/unrelated and WooCommerce options retained' );
require_once ABSPATH . 'wp-admin/includes/dashboard.php'; set_current_screen( 'dashboard' ); ADWC_Plugin::register_widget();
u4_assert( isset( $GLOBALS['wp_meta_boxes']['dashboard']['normal']['core']['advanced-dashboard-admin-widget'] ), 'Replacement registers the same widget ID' );
$data = ADWC_Analytics::read( ADWC_Date_Range::resolve( '30' ) );
u4_assert( 'ready' === $data['state'] && 3 === $data['totals']['orders_count'], 'Replacement reads the three original synthetic orders through Analytics' );
ADWC_Operations::save( 96, array( 'wc-processing', 'wc-on-hold' ) );
deactivate_plugins( $plugin ); $active = activate_plugin( $plugin );
u4_assert( ! is_wp_error( $active ) && is_plugin_active( $plugin ) && 96 === ADWC_Operations::preferences()['age_hours'], 'Reactivation retains new preferences and active state' );
foreach ( $baseline['prefs'] as $key => $value ) { u4_assert( get_user_option( $key ) === $value, 'Reactivation retains ' . $key ); }
WP_CLI::success( 'Real 0.2.5 file upgrade and reactivation verified.' );
