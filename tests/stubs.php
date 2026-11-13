<?php
/** Small API doubles. These tests do not represent a WordPress/WooCommerce integration. */
namespace { if ( 'cli' !== PHP_SAPI ) { http_response_code( 404 ); exit; } }
namespace Automattic\WooCommerce\Admin {
	class ReportsSync {
		public static function get_import_stats() { return $GLOBALS['import_stats']; }
	}
}
namespace Automattic\WooCommerce\Internal\Admin\Schedulers {
	class OrdersScheduler {
		public static function get_failed_order_imports() { return $GLOBALS['failed_imports']; }
		public static function is_scheduled_import_enabled() { return $GLOBALS['scheduled']; }
	}
}
namespace Automattic\WooCommerce\Internal\Admin {
	class Settings {
		public static function get_default_excluded_order_statuses() { return array( 'pending', 'failed', 'cancelled' ); }
		public static function get_valid_order_statuses_or_default( $value, $default ) { return is_array( $value ) ? $value : $default; }
	}
}
namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	class WP_Error {
		private $code; private $message; private $data;
		public function __construct( $code, $message, $data = null ) { $this->code = $code; $this->message = $message; $this->data = $data; }
		public function get_error_code() { return $this->code; }
		public function get_error_message() { return $this->message; }
		public function get_error_data() { return $this->data; }
	}
	class WP_REST_Request {
		private $params = array(); private $headers = array();
		public function __construct( $method = 'GET', $route = '' ) {}
		public function set_query_params( $params ) { $this->params = $params; }
		public function get_param( $name ) { return $this->params[ $name ] ?? null; }
		public function set_header( $name, $value ) { $this->headers[ $name ] = $value; }
		public function get_header( $name ) { return $this->headers[ $name ] ?? ''; }
		public function params() { return $this->params; }
	}
	class WP_REST_Response {
		public $headers = array(); private $data; private $status;
		public function __construct( $data, $status = 200 ) { $this->data = $data; $this->status = $status; }
		public function get_data() { return $this->data; }
		public function is_error() { return $this->status >= 400; }
		public function header( $name, $value ) { $this->headers[ $name ] = $value; }
	}
	class WC_Admin_Settings {
		public static function get_option( $key, $default ) { return get_option( $key, $default ); }
	}
	class ADWC_Test_DB {
		public $prefix = 'wp_'; public $last_error = ''; public $suppressed = false;
		public function suppress_errors( $value ) { $old = $this->suppressed; $this->suppressed = $value; return $old; }
		public function prepare( $sql, $args ) { $GLOBALS['lookup_query'] = array( $sql, $args ); return $sql; }
		public function get_col( $sql ) { $this->last_error = $GLOBALS['db_error']; return $GLOBALS['lookup_refunds']; }
	}
	class ADWC_Test_Order {
		private $id; private $parent; private $currency; private $status;
		public function __construct( $id, $parent = 0, $currency = 'PLN', $status = 'completed' ) { $this->id = $id; $this->parent = $parent; $this->currency = $currency; $this->status = $status; }
		public function get_id() { return $this->id; }
		public function get_parent_id() { return $this->parent; }
		public function get_currency() { return $this->currency; }
		public function get_status() { return $this->status; }
		public function get_order_number() { return (string) $this->id; }
		public function get_date_created() { return new \DateTimeImmutable( '2026-11-01', wp_timezone() ); }
		public function get_edit_order_url() { return 'https://example.test/wp-admin/post.php?post=' . $this->id . '&action=edit'; }
	}
	function __( $text, $domain = '' ) { return $text; }
	function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $text ) { return esc_html( $text ); }
	function esc_url( $text ) { return esc_html( $text ); }
	function esc_html__( $text, $domain ) { return esc_html( $text ); }
	function esc_html_e( $text, $domain ) { echo esc_html( $text ); }
	function esc_attr_e( $text, $domain ) { echo esc_attr( $text ); }
	function current_user_can( $cap, ...$args ) { return 'edit_shop_order' === $cap ? ! empty( $GLOBALS['caps']['edit_shop_orders'] ) && ! in_array( $args[0], $GLOBALS['denied_orders'], true ) : ! empty( $GLOBALS['caps'][ $cap ] ); }
	function wp_timezone() { return new DateTimeZone( $GLOBALS['timezone'] ); }
	function wp_timezone_string() { return $GLOBALS['timezone']; }
	function current_datetime() { return new DateTimeImmutable( '2026-11-13 12:30:00', wp_timezone() ); }
	function wp_date( $format, $time = null, $timezone = null ) { return null === $time ? current_datetime()->format( $format ) : ( new DateTimeImmutable( '@' . $time ) )->setTimezone( $timezone ?: wp_timezone() )->format( $format ); }
	function is_wp_error( $value ) { return $value instanceof WP_Error; }
	function get_option( $key, $default = false ) { return $GLOBALS['options'][ $key ] ?? $default; }
	function get_current_user_id() { return $GLOBALS['user_id']; }
	function get_user_option( $key ) { return $GLOBALS['user_options'][ get_current_user_id() ][ $key ] ?? false; }
	function update_user_option( $id, $key, $value, $global = false ) { $GLOBALS['user_options'][ $id ][ $key ] = $value; return true; }
	function human_time_diff( $from, $to ) { return (int) floor( ( $to - $from ) / 3600 ) . ' hours'; }
	define( 'HOUR_IN_SECONDS', 3600 );
	function apply_filters( $name, $value ) { return $GLOBALS['filters'][ $name ] ?? $value; }
	function add_action( $name, $callback ) { $GLOBALS['hooks'][ $name ][] = $callback; }
	function is_network_admin() { return $GLOBALS['network']; }
	function wp_add_dashboard_widget( $id, $title, $callback ) { $GLOBALS['widgets'][] = $id; }
	function wp_enqueue_style( ...$args ) { $GLOBALS['assets'][] = $args; }
	function wp_enqueue_script( ...$args ) { $GLOBALS['assets'][] = $args; }
	function plugins_url( $path, $file ) { return 'https://example.test/plugins/advanced-dashboard-for-woocommerce/' . $path; }
	function rest_url( $path ) { return ! empty( $GLOBALS['rest_plain'] ) ? '/?rest_route=/' . $path : ( $GLOBALS['rest_url'] ?? 'https://example.test/wp-json/' . $path ); }
	function admin_url( $path ) { return 'https://example.test/wp-admin/' . $path; }
	function wp_create_nonce( $name ) { return 'valid-nonce'; }
	function wp_verify_nonce( $nonce, $name ) { return 'valid-nonce' === $nonce && 'wp_rest' === $name; }
	function get_user_locale() { return 'pl_PL'; }
	function get_current_screen() { return (object) array( 'id' => $GLOBALS['screen'] ); }
	function register_rest_route( $namespace, $route, $args ) { $GLOBALS['registered_route'] = $args; }
	function rest_get_server() { return new class {
		public function get_routes() { return $GLOBALS['routes']; }
	}; }
	function rest_do_request( $request ) { $GLOBALS['analytics_queries'][] = $request->params(); return new WP_REST_Response( $GLOBALS['payload'], $GLOBALS['analytics_status'] ); }
	function wc_get_order_statuses() { return array( 'wc-pending' => 'Pending', 'wc-processing' => 'Processing', 'wc-on-hold' => 'On hold', 'wc-completed' => 'Completed', 'wc-refunded' => 'Refunded', 'wc-failed' => 'Failed', 'wc-cancelled' => 'Cancelled' ); }
	function wc_get_order_status_name( $status ) { return wc_get_order_statuses()[ $status ] ?? $status; }
	function get_woocommerce_currency() { return 'PLN'; }
	function wc_orders_count( $status ) { $GLOBALS['count_queries'][] = $status; return 'processing' === $status ? 4 : 2; }
	function wc_get_orders( $query ) {
		$GLOBALS['order_queries'][] = $query;
		if ( isset( $query['date_created'] ) && 0 === strpos( $query['date_created'], '<' ) ) return $GLOBALS['queue_orders'];
		if ( isset( $query['date_modified'] ) ) return 0 === strpos( $query['date_modified'], '>' ) ? $GLOBALS['changed'] : $GLOBALS['same_second'];
		if ( 'shop_order_refund' === $query['type'] ) return $GLOBALS['refunds'];
		return (object) array( 'total' => isset( $query['currency'] ) ? $GLOBALS['currency_count'] : $GLOBALS['source_count'], 'orders' => array() );
	}
	function wc_get_order( $id ) { return $GLOBALS['parents'][ $id ] ?? false; }
	function wc_price( $value, $args ) { return '<span>' . number_format( (float) $value, 2, ',', ' ' ) . '&nbsp;zł</span>'; }
	function wp_strip_all_tags( $text ) { return strip_tags( $text ); }
	function number_format_i18n( $value, $decimals ) { return number_format( $value, $decimals, ',', ' ' ); }
	function adwc_reset() {
		unset( $GLOBALS['rest_url'], $GLOBALS['rest_plain'] );
		$GLOBALS['caps'] = array( 'view_woocommerce_reports' => true, 'edit_shop_orders' => true, 'manage_options' => true );
		$GLOBALS['timezone'] = 'Europe/Warsaw';
		$GLOBALS['user_id'] = 1;
		$GLOBALS['user_options'] = $GLOBALS['queue_orders'] = $GLOBALS['denied_orders'] = array();
		$GLOBALS['options'] = $GLOBALS['filters'] = $GLOBALS['assets'] = $GLOBALS['widgets'] = array();
		$GLOBALS['analytics_queries'] = $GLOBALS['order_queries'] = $GLOBALS['count_queries'] = array();
		$GLOBALS['import_stats'] = array( 'is_importing' => false );
		$GLOBALS['failed_imports'] = array();
		$GLOBALS['scheduled'] = $GLOBALS['network'] = false;
		$GLOBALS['source_count'] = $GLOBALS['currency_count'] = 0;
		$GLOBALS['refunds'] = $GLOBALS['parents'] = $GLOBALS['changed'] = $GLOBALS['same_second'] = $GLOBALS['lookup_refunds'] = array();
		$GLOBALS['db_error'] = '';
		$GLOBALS['wpdb'] = new ADWC_Test_DB();
		$GLOBALS['analytics_status'] = 200;
		$GLOBALS['screen'] = 'dashboard';
		$GLOBALS['payload'] = array( 'totals' => array( 'net_revenue' => 0, 'orders_count' => 0, 'num_items_sold' => 0 ), 'intervals' => array() );
		$GLOBALS['routes'] = array( '/wc-analytics/reports/revenue/stats' => array( array( 'methods' => array( 'GET' => true ), 'args' => array( 'date_type' => array( 'enum' => array( 'date_created', 'date_paid' ) ) ) ) ) );
	}
}
