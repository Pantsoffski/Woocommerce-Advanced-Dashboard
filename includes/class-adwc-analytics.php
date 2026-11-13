<?php
/**
 * Read WooCommerce Analytics aggregates; never rebuild or scan orders for chart totals.
 *
 * @package AdvancedDashboardForWooCommerce
 */

defined( 'ABSPATH' ) || exit;

/** Validates and formats native WooCommerce Analytics aggregates. */
final class ADWC_Analytics {
	const ROUTE              = '/wc-analytics/reports/revenue/stats';
	const REFUND_CHECK_LIMIT = 100;

	/**
	 * Read and validate data for the current authorized user.
	 *
	 * @param array $range Range.
	 * @return mixed Validated result or error; hook callbacks return no value.
	 * @throws RuntimeException When authorization or data integrity cannot be confirmed.
	 */
	public static function read( $range ) {
		if ( ! ADWC_Plugin::can_view() || ! ADWC_Plugin::available() ) {
			throw new RuntimeException( 'Unauthorized data access.' );
		}
		$base    = array(
			'from'     => $range['start']->format( 'Y-m-d' ),
			'to'       => $range['end']->format( 'Y-m-d' ),
			'timezone' => wp_timezone_string(),
			'state'    => 'unavailable',
			'message'  => '',
			'rows'     => array(),
		);
		$problem = self::availability( $range );
		if ( $problem ) {
			$base['message'] = $problem;
			return $base;
		}

		$request = new WP_REST_Request( 'GET', self::ROUTE );
		$request->set_query_params(
			array(
				'after'     => $range['start']->format( 'Y-m-d\TH:i:s' ),
				'before'    => $range['end']->format( 'Y-m-d\TH:i:s' ),
				'date_type' => 'date_created',
				'interval'  => 'day',
				'per_page'  => 100,
				'page'      => 1,
				'orderby'   => 'date',
				'order'     => 'asc',
				'fields'    => array( 'net_revenue', 'orders_count', 'num_items_sold' ),
			)
		);
		// An in-process REST call retains WooCommerce's own permissions, validation and data store.
		$response = rest_do_request( $request );
		if ( $response->is_error() ) {
			$base['message'] = __( 'WooCommerce Analytics could not provide this report. Check Analytics settings and try again.', 'advanced-dashboard-for-woocommerce' );
			return $base;
		}
		$data = $response->get_data();
		if ( is_array( $data ) && isset( $data['totals'] ) && $data['totals'] instanceof stdClass ) {
			$data['totals'] = get_object_vars( $data['totals'] );
		}
		$rows = self::normalize( $data, $range );
		if ( is_wp_error( $rows ) ) {
			$base['message'] = $rows->get_error_message();
			return $base;
		}

		$currency = get_woocommerce_currency();
		$statuses = self::included_statuses();
		$problem  = self::integrity( $range, $statuses, $currency, (int) $data['totals']['orders_count'] );
		if ( $problem ) {
			$base['message'] = $problem;
			return $base;
		}
		$base['state']      = 'ready';
		$base['currency']   = $currency;
		$base['statuses']   = array_values( array_map( 'wc_get_order_status_name', $statuses ) );
		$base['checked_at'] = wp_date( 'Y-m-d H:i:s T' );
		$base['totals']     = self::values( $data['totals'], $currency );
		$base['rows']       = array_map(
			static function ( $row ) use ( $currency ) {
				return array_merge( array( 'date' => $row['date'] ), self::values( $row, $currency ) );
			},
			$rows
		);
		$base['empty']      = 0 === (int) $data['totals']['orders_count'] && ! array_filter(
			$rows,
			static function ( $row ) {
				return 0.0 !== (float) $row['net_revenue'] || 0.0 !== (float) $row['num_items_sold'];
			}
		);
		return $base;
	}

	/**
	 * Check Analytics route support and import readiness.
	 *
	 * @param array $range Range.
	 * @return mixed Validated result or error; hook callbacks return no value.
	 */
	private static function availability( $range ) {
		$unavailable = __( 'Analytics is unavailable or its import status cannot be checked. Enable WooCommerce Analytics and check its settings.', 'advanced-dashboard-for-woocommerce' );
		if ( 'yes' !== get_option( 'woocommerce_analytics_enabled', 'yes' ) || ! is_callable( array( 'Automattic\WooCommerce\Admin\ReportsSync', 'get_import_stats' ) ) ) {
			return $unavailable;
		}
		$routes = rest_get_server()->get_routes();
		if ( ! isset( $routes[ self::ROUTE ] ) ) {
			// Modern WooCommerce lazily registers wc-analytics during REST dispatch.
			// OPTIONS loads the native route/schema without running an aggregate query.
			rest_do_request( new WP_REST_Request( 'OPTIONS', self::ROUTE ) );
			$routes = rest_get_server()->get_routes();
		}
		$supported = false;
		foreach ( $routes[ self::ROUTE ] ?? array() as $endpoint ) {
			if ( is_array( $endpoint ) && ! empty( $endpoint['methods']['GET'] ) && in_array( 'date_created', $endpoint['args']['date_type']['enum'] ?? array(), true ) ) {
				$supported = true;
			}
		}
		if ( ! $supported ) {
			return $unavailable;
		}
		$stats = \Automattic\WooCommerce\Admin\ReportsSync::get_import_stats();
		if ( ! empty( $stats['is_importing'] ) || ( isset( $stats['orders']['total'], $stats['orders']['imported'] ) && $stats['orders']['imported'] < $stats['orders']['total'] ) ) {
			return __( 'Analytics is importing or updating orders. Charts will be available after synchronization finishes.', 'advanced-dashboard-for-woocommerce' );
		}
		// Absence of a historical-import marker alone does not imply an empty store.
		$imported_from = $stats['imported_from'] ?? null;
		if ( $imported_from && '-1' !== (string) $imported_from && (string) $imported_from > $range['start']->format( 'Y-m-d H:i:s' ) ) {
			return __( 'This range starts before the recorded Analytics import. Import the required history in Analytics settings.', 'advanced-dashboard-for-woocommerce' );
		}
		$scheduler = 'Automattic\WooCommerce\Internal\Admin\Schedulers\OrdersScheduler';
		if ( is_callable( array( $scheduler, 'get_failed_order_imports' ) ) ) {
			$failed = $scheduler::get_failed_order_imports();
			if ( ! empty( $failed['ids'] ) || ! empty( $failed['overflow'] ) ) {
				return __( 'Analytics has failed order imports. Resolve them in Analytics settings before using these charts.', 'advanced-dashboard-for-woocommerce' );
			}
		}
		if ( is_callable( array( $scheduler, 'is_scheduled_import_enabled' ) ) && $scheduler::is_scheduled_import_enabled() ) {
			$cursor = get_option( 'woocommerce_admin_scheduler_last_processed_order_modified_date' );
			$time   = is_string( $cursor ) ? DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $cursor, new DateTimeZone( 'UTC' ) ) : false;
			if ( ! $time || $time->format( 'Y-m-d H:i:s' ) !== $cursor ) {
				return __( 'Scheduled Analytics synchronization has no valid checkpoint yet. Wait for the next import.', 'advanced-dashboard-for-woocommerce' );
			}
			$query   = array(
				'type'   => array( 'shop_order', 'shop_order_refund' ),
				'status' => array_keys( wc_get_order_statuses() ),
				'limit'  => 1,
				'return' => 'ids',
			);
			$changed = wc_get_orders( array_merge( $query, array( 'date_modified' => '>' . $time->getTimestamp() ) ) );
			if ( ! $changed ) {
				// Respect the scheduler's ID tie-breaker within the checkpoint second.
				$same_second = wc_get_orders(
					array_merge(
						$query,
						array(
							'date_modified' => (string) $time->getTimestamp(),
							'orderby'       => 'ID',
							'order'         => 'DESC',
						)
					)
				);
				$changed     = $same_second && (int) $same_second[0] > (int) get_option( 'woocommerce_admin_scheduler_last_processed_order_id', 0 );
			}
			if ( $changed ) {
				return __( 'Orders changed since the last scheduled Analytics synchronization. Wait for Analytics to catch up.', 'advanced-dashboard-for-woocommerce' );
			}
		}
		return '';
	}

	/**
	 * Resolve the native Analytics status policy.
	 *
	 * @return mixed Validated result or error; hook callbacks return no value.
	 * @throws RuntimeException When authorization or data integrity cannot be confirmed.
	 */
	private static function included_statuses() {
		$settings = 'Automattic\WooCommerce\Internal\Admin\Settings';
		// WC < 11.2 uses these same built-in defaults without the Settings helpers.
		$defaults = is_callable( array( $settings, 'get_default_excluded_order_statuses' ) ) ? $settings::get_default_excluded_order_statuses() : array( 'pending', 'failed', 'cancelled' );
		$excluded = WC_Admin_Settings::get_option( 'woocommerce_excluded_report_order_statuses', $defaults );
		$excluded = is_callable( array( $settings, 'get_valid_order_statuses_or_default' ) ) ? $settings::get_valid_order_statuses_or_default( $excluded, $defaults ) : ( is_array( $excluded ) ? $excluded : $defaults );
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Reuse WooCommerce's native status policy.
		$excluded = apply_filters( 'woocommerce_analytics_excluded_order_statuses', array_merge( array( 'auto-draft', 'trash' ), $excluded ) );
		if ( ! is_array( $excluded ) ) {
			throw new RuntimeException( 'Invalid status filter.' );
		}
		$excluded = array_map(
			static function ( $status ) {
				return 'wc-' . preg_replace( '/^wc-/', '', $status );
			},
			$excluded
		);
		return array_values( array_diff( array_keys( wc_get_order_statuses() ), $excluded ) );
	}

	/**
	 * Verify source counts and refund currency without computing chart totals.
	 *
	 * @param array  $range Range.
	 * @param array  $statuses Statuses.
	 * @param string $currency Currency.
	 * @param int    $reported_count Reported count.
	 * @return mixed Validated result or error; hook callbacks return no value.
	 * @throws RuntimeException When authorization or data integrity cannot be confirmed.
	 */
	private static function integrity( $range, $statuses, $currency, $reported_count ) {
		if ( ! $statuses ) {
			return __( 'Analytics excludes all registered order statuses. Review Analytics settings.', 'advanced-dashboard-for-woocommerce' );
		}
		$query = array(
			'type'         => 'shop_order',
			'status'       => $statuses,
			'date_created' => $range['start']->getTimestamp() . '...' . $range['end']->getTimestamp(),
			'limit'        => 1,
			'paginate'     => true,
			'return'       => 'ids',
		);
		$all   = wc_get_orders( $query );
		if ( ! is_object( $all ) || ! isset( $all->total ) ) {
			throw new RuntimeException( 'Order count is unavailable.' );
		}
		$in_currency = wc_get_orders( array_merge( $query, array( 'currency' => $currency ) ) );
		if ( ! is_object( $in_currency ) || ! isset( $in_currency->total ) ) {
			throw new RuntimeException( 'Currency check is unavailable.' );
		}
		if ( (int) $all->total !== (int) $in_currency->total ) {
			return __( 'This range contains orders in a different or unknown currency. Charts are withheld because Analytics does not separate currencies. No currency conversion has been applied.', 'advanced-dashboard-for-woocommerce' );
		}
		if ( (int) $all->total !== $reported_count ) {
			return __( 'Analytics and the order store disagree for this range. Data may be unimported, outdated or excluded by another extension. Check Analytics settings; these are not zero sales.', 'advanced-dashboard-for-woocommerce' );
		}
		// Refunds can belong to orders created BEFORE this range. Check their parent currency too.
		// This is a bounded integrity check, never a fallback for computing chart totals.
		$refunds = wc_get_orders(
			array(
				'type'         => 'shop_order_refund',
				'status'       => array_keys( wc_get_order_statuses() ),
				'date_created' => $query['date_created'],
				'limit'        => self::REFUND_CHECK_LIMIT + 1,
			)
		);
		if ( count( $refunds ) > self::REFUND_CHECK_LIMIT ) {
			return __( 'There are too many refunds to safely verify currency in this range. Choose a shorter range (up to 100 refund records).', 'advanced-dashboard-for-woocommerce' );
		}
		$expected_refunds = array();
		foreach ( $refunds as $refund ) {
			$parent = wc_get_order( $refund->get_parent_id() );
			if ( ! $parent ) {
				return __( 'A refund has no accessible parent order. Analytics currency cannot be verified.', 'advanced-dashboard-for-woocommerce' );
			}
			if ( in_array( 'wc-' . $parent->get_status(), $statuses, true ) ) {
				if ( $parent->get_currency() !== $currency ) {
					return __( 'This range contains refunds in another currency. Choose a range with a single store currency.', 'advanced-dashboard-for-woocommerce' );
				}
				$expected_refunds[] = (int) $refund->get_id();
			}
		}
		// Analytics does not expose a refund-record count in this endpoint. Audit IDs only
		// in its lookup table, never posts/postmeta or a second source of financial totals.
		global $wpdb;
		$placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
		$args         = array_merge( array( $range['start']->format( 'Y-m-d H:i:s' ), $range['end']->format( 'Y-m-d H:i:s' ) ), $statuses );
		$suppressed   = $wpdb->suppress_errors( true );
		try {
			// Fresh integrity probe: caching could falsely validate an incomplete import.
			// All values are prepared; the interpolated fragment consists exclusively of generated %s tokens.
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$imported_refunds = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT order_id FROM {$wpdb->prefix}wc_order_stats WHERE parent_id > 0 AND date_created >= %s AND date_created <= %s AND status IN ($placeholders) LIMIT 101",
					$args
				)
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( $wpdb->last_error ) {
				throw new RuntimeException( 'Analytics refund verification failed.' );
			}
		} finally {
			$wpdb->suppress_errors( $suppressed );
		}
		$imported_refunds = array_map( 'intval', $imported_refunds );
		sort( $expected_refunds );
		sort( $imported_refunds );
		if ( $expected_refunds !== $imported_refunds ) {
			return __( 'Analytics refund records do not match the order store. Wait for synchronization or check the historical import; zero sales cannot be confirmed.', 'advanced-dashboard-for-woocommerce' );
		}
		return '';
	}

	/**
	 * Validate report buckets and fill missing calendar days.
	 *
	 * @param array $data Data.
	 * @param array $range Range.
	 * @return mixed Validated result or error; hook callbacks return no value.
	 */
	public static function normalize( $data, $range ) {
		$error = new WP_Error( 'adwc_incomplete', __( 'Analytics returned an incomplete or inconsistent report. Check its import status and try again.', 'advanced-dashboard-for-woocommerce' ) );
		if ( ! is_array( $data ) || ! isset( $data['totals'], $data['intervals'] ) || ! is_array( $data['intervals'] ) ) {
			return $error;
		}
		$keys = array( 'net_revenue', 'orders_count', 'num_items_sold' );
		$sum  = array_fill_keys( $keys, 0 );
		$rows = array();
		for ( $day = $range['start']; $day <= $range['end']; $day = $day->modify( '+1 day' ) ) {
			$date          = $day->format( 'Y-m-d' );
			$rows[ $date ] = array_merge( array( 'date' => $date ), $sum );
		}
		$seen = array();
		foreach ( $data['intervals'] as $interval ) {
			// In-process REST responses are not JSON round-tripped. WooCommerce can
			// retain stdClass buckets/subtotals here even though HTTP clients see objects.
			if ( $interval instanceof stdClass ) {
				$interval = get_object_vars( $interval );
			}
			if ( ! is_array( $interval ) ) {
				return $error;
			}
			if ( isset( $interval['subtotals'] ) && $interval['subtotals'] instanceof stdClass ) {
				$interval['subtotals'] = get_object_vars( $interval['subtotals'] );
			}
			$date = isset( $interval['date_start'] ) && is_string( $interval['date_start'] ) ? substr( $interval['date_start'], 0, 10 ) : '';
			if ( ! isset( $rows[ $date ] ) || isset( $seen[ $date ] ) || ! isset( $interval['subtotals'] ) ) {
				return $error;
			}
			$seen[ $date ] = true;
			foreach ( $keys as $key ) {
				$value = $interval['subtotals'][ $key ] ?? null;
				if ( ! is_numeric( $value ) || ! is_finite( (float) $value ) || ( 'orders_count' === $key && ( $value < 0 || (float) (int) $value !== (float) $value ) ) ) {
					return $error;
				}
				$rows[ $date ][ $key ] = (float) $value;
				$sum[ $key ]          += (float) $value;
			}
		}
		foreach ( $keys as $key ) {
			$total = $data['totals'][ $key ] ?? null;
			if ( ! is_numeric( $total ) || ! is_finite( (float) $total ) || abs( $sum[ $key ] - (float) $total ) > 0.001 ) {
				return $error;
			}
		}
		return array_values( $rows );
	}

	/**
	 * Format validated aggregate values using store settings.
	 *
	 * @param array  $values Values.
	 * @param string $currency Currency.
	 * @return mixed Validated result or error; hook callbacks return no value.
	 */
	private static function values( $values, $currency ) {
		return array(
			'net_revenue'    => (float) $values['net_revenue'],
			'orders_count'   => (int) $values['orders_count'],
			'num_items_sold' => (float) $values['num_items_sold'],
			'formatted'      => array(
				'net_revenue'    => html_entity_decode( wp_strip_all_tags( wc_price( $values['net_revenue'], array( 'currency' => $currency ) ) ), ENT_QUOTES, 'UTF-8' ),
				'orders_count'   => number_format_i18n( $values['orders_count'], 0 ),
				// phpcs:ignore WordPress.PHP.YodaConditions.NotYoda -- Both compared values are computed numeric casts.
				'num_items_sold' => number_format_i18n( $values['num_items_sold'], (float) $values['num_items_sold'] === (float) (int) $values['num_items_sold'] ? 0 : 2 ),
			),
		);
	}
}
