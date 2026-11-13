<?php
/**
 * Dashboard-only presentation and authenticated data boundary.
 *
 * @package AdvancedDashboardForWooCommerce
 */

defined( 'ABSPATH' ) || exit;

/** Registers dashboard presentation and authenticated REST boundaries. */
final class ADWC_Plugin {
	/**
	 * Main plugin file path.
	 *
	 * @var string
	 */
	private static $file;

	/**
	 * Register plugin hooks without loading store data.
	 *
	 * @param string $file File.
	 * @return mixed Validated result or error; hook callbacks return no value.
	 */
	public static function init( $file ) {
		self::$file = $file;
		add_action( 'wp_dashboard_setup', array( __CLASS__, 'register_widget' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'admin_notices', array( __CLASS__, 'dependency_notice' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Check that the required WooCommerce APIs are available.
	 *
	 * @return mixed Validated result or error; hook callbacks return no value.
	 */
	public static function available() {
		return class_exists( 'WooCommerce' ) && function_exists( 'wc_get_orders' ) && function_exists( 'wc_orders_count' );
	}

	/**
	 * Check the current user capability.
	 *
	 * @return mixed Validated result or error; hook callbacks return no value.
	 */
	public static function can_view() {
		return current_user_can( 'view_woocommerce_reports' );
	}

	/**
	 * Register the preserved dashboard widget for authorized users.
	 *
	 * @return mixed Validated result or error; hook callbacks return no value.
	 */
	public static function register_widget() {
		if ( ! self::available() || ! self::can_view() || is_network_admin() ) {
			return;
		}
		wp_add_dashboard_widget( 'advanced-dashboard-admin-widget', __( 'Advanced Dashboard for WooCommerce', 'advanced-dashboard-for-woocommerce' ), array( __CLASS__, 'render' ) );
	}

	/**
	 * Load local assets only on the main dashboard.
	 *
	 * @param string $hook Hook.
	 * @return mixed Validated result or error; hook callbacks return no value.
	 */
	public static function enqueue( $hook ) {
		if ( 'index.php' !== $hook || is_network_admin() || ! self::available() || ! self::can_view() ) {
			return;
		}
		wp_enqueue_style( 'adwc-dashboard', plugins_url( 'assets/dashboard.css', self::$file ), array(), (string) filemtime( dirname( self::$file ) . '/assets/dashboard.css' ) );
		wp_enqueue_script( 'adwc-dashboard', plugins_url( 'assets/dashboard.js', self::$file ), array(), (string) filemtime( dirname( self::$file ) . '/assets/dashboard.js' ), true );
	}

	/**
	 * Render the dashboard dependency notice for administrators.
	 *
	 * @return mixed Validated result or error; hook callbacks return no value.
	 */
	public static function dependency_notice() {
		$screen = get_current_screen();
		if ( self::available() || ! $screen || 'dashboard' !== $screen->id || ! current_user_can( 'manage_options' ) || is_network_admin() ) {
			return;
		}
		echo '<div class="notice notice-info"><p>' . esc_html__( 'Advanced Dashboard for WooCommerce needs an active WooCommerce installation. No store data has been loaded.', 'advanced-dashboard-for-woocommerce' ) . '</p></div>';
	}

	/**
	 * Render the authorized widget template.
	 *
	 * @return mixed Validated result or error; hook callbacks return no value.
	 */
	public static function render() {
		if ( ! self::available() || ! self::can_view() ) {
			return;
		}
		require dirname( self::$file ) . '/templates/dashboard.php';
	}

	/**
	 * Register authenticated report and preference endpoints.
	 *
	 * @return mixed Validated result or error; hook callbacks return no value.
	 */
	public static function register_routes() {
		register_rest_route(
			'advanced-dashboard-for-woocommerce/v1',
			'/summary',
			array(
				'methods'             => 'GET',
				'permission_callback' => array( __CLASS__, 'permission' ),
				'callback'            => array( __CLASS__, 'summary' ),
			)
		);
		register_rest_route(
			'advanced-dashboard-for-woocommerce/v1',
			'/operations',
			array(
				'methods'             => 'GET',
				'permission_callback' => array( __CLASS__, 'operations_permission' ),
				'callback'            => array( __CLASS__, 'operations' ),
			)
		);
		register_rest_route(
			'advanced-dashboard-for-woocommerce/v1',
			'/operations/preferences',
			array(
				'methods'             => 'POST',
				'permission_callback' => array( __CLASS__, 'operations_permission' ),
				'callback'            => array( __CLASS__, 'save_preferences' ),
			)
		);
	}

	/**
	 * Authorize access to aggregate reports.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed Validated result or error; hook callbacks return no value.
	 */
	public static function permission( $request ) {
		return self::authorize( $request, 'view_woocommerce_reports' );
	}

	/**
	 * Authorize operational order access.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed Validated result or error; hook callbacks return no value.
	 */
	public static function operations_permission( $request ) {
		return self::authorize( $request, 'edit_shop_orders' );
	}

	/**
	 * Validate capability and the REST session nonce.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param string          $capability Capability.
	 * @return mixed Validated result or error; hook callbacks return no value.
	 */
	private static function authorize( $request, $capability ) {
		if ( ! current_user_can( $capability ) ) {
			return new WP_Error( 'adwc_forbidden', __( 'You do not have permission to view store reports.', 'advanced-dashboard-for-woocommerce' ), array( 'status' => 403 ) );
		}
		// This dashboard endpoint uses the current user's cookie session only.
		if ( ! wp_verify_nonce( $request->get_header( 'X-WP-Nonce' ), 'wp_rest' ) ) {
			return new WP_Error( 'adwc_nonce', __( 'Your session has expired. Reload the dashboard.', 'advanced-dashboard-for-woocommerce' ), array( 'status' => 403 ) );
		}
		return true;
	}

	/**
	 * Serve the bounded operational queue.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed Validated result or error; hook callbacks return no value.
	 */
	public static function operations( $request ) {
		return self::operational_response( $request, false );
	}

	/**
	 * Save the current user preferences through the protected endpoint.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed Validated result or error; hook callbacks return no value.
	 */
	public static function save_preferences( $request ) {
		return self::operational_response( $request, true );
	}

	/**
	 * Dispatch an operational read or preference write.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param bool            $save Save.
	 * @return mixed Validated result or error; hook callbacks return no value.
	 */
	private static function operational_response( $request, $save ) {
		$permission = self::operations_permission( $request );
		if ( is_wp_error( $permission ) ) {
			return $permission; }
		if ( ! self::available() ) {
			return new WP_Error( 'adwc_woocommerce_missing', __( 'WooCommerce is unavailable.', 'advanced-dashboard-for-woocommerce' ), array( 'status' => 503 ) );
		}
		try {
			if ( $save ) {
				$result = ADWC_Operations::save( $request->get_param( 'age_hours' ), $request->get_param( 'statuses' ) );
			} else {
				$result = ADWC_Operations::read();
			}
			if ( is_wp_error( $result ) ) {
				return $result; }
			$response = new WP_REST_Response( $result );
			$response->header( 'Cache-Control', 'no-store, private' );
			return $response;
		} catch ( Throwable $error ) {
			return new WP_Error( 'adwc_operations_error', __( 'Order information could not be loaded or saved. Try again.', 'advanced-dashboard-for-woocommerce' ), array( 'status' => 503 ) );
		}
	}

	/**
	 * Serve validated daily aggregates without exposing exception details.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed Validated result or error; hook callbacks return no value.
	 */
	public static function summary( $request ) {
		$permission = self::permission( $request );
		if ( is_wp_error( $permission ) ) {
			return $permission;
		}
		if ( ! self::available() ) {
			return new WP_Error( 'adwc_woocommerce_missing', __( 'WooCommerce is unavailable.', 'advanced-dashboard-for-woocommerce' ), array( 'status' => 503 ) );
		}
		$range = ADWC_Date_Range::resolve( $request->get_param( 'range' ) ?? 'month', $request->get_param( 'from' ), $request->get_param( 'to' ) );
		if ( is_wp_error( $range ) ) {
			return $range;
		}
		try {
			$result = ADWC_Analytics::read( $range );
			// Operational counts have a separate capability and use the authoritative WC data store.
			$result['counts'] = current_user_can( 'edit_shop_orders' ) ? array(
				'processing' => (int) wc_orders_count( 'processing' ),
				'on_hold'    => (int) wc_orders_count( 'on-hold' ),
			) : null;
			$response         = new WP_REST_Response( $result );
			$response->header( 'Cache-Control', 'no-store, private' );
			return $response;
		} catch ( Throwable $error ) {
			// Never return SQL, paths, order details or exception messages to the browser.
			return new WP_Error( 'adwc_data_error', __( 'Store data could not be loaded. Check WooCommerce Analytics and try again.', 'advanced-dashboard-for-woocommerce' ), array( 'status' => 503 ) );
		}
	}
}
