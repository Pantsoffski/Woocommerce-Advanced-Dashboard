<?php
/**
 * Bounded, read-only operational queue from WooCommerce's authoritative order store.
 *
 * @package AdvancedDashboardForWooCommerce
 */

defined( 'ABSPATH' ) || exit;

/** Reads a bounded operational queue and user preferences. */
final class ADWC_Operations {
	const USER_OPTION  = 'adwc_operational_preferences';
	const MAX_ORDERS   = 10;
	const MAX_STATUSES = 20;
	const MAX_HOURS    = 8760;

	/**
	 * Check the current user capability.
	 *
	 * @return mixed Validated result or error; hook callbacks return no value.
	 */
	public static function can_view() {
		return current_user_can( 'edit_shop_orders' );
	}

	/**
	 * List selectable open and registered custom order statuses.
	 *
	 * @return mixed Validated result or error; hook callbacks return no value.
	 */
	public static function available_statuses() {
		$statuses = wc_get_order_statuses();
		// Completed/cancelled/refunded orders are not part of this open-order queue.
		foreach ( array( 'wc-completed', 'wc-cancelled', 'wc-refunded', 'wc-failed', 'wc-checkout-draft', 'wc-auto-draft', 'wc-trash' ) as $closed ) {
			unset( $statuses[ $closed ] );
		}
		return $statuses;
	}

	/**
	 * Read validated site-specific preferences for the current user.
	 *
	 * @return mixed Validated result or error; hook callbacks return no value.
	 * @throws RuntimeException When authorization or data integrity cannot be confirmed.
	 */
	public static function preferences() {
		if ( ! self::can_view() ) {
			throw new RuntimeException( 'Unauthorized operational preferences access.' );
		}
		$saved    = get_user_option( self::USER_OPTION );
		$saved    = is_array( $saved ) ? $saved : array();
		$hours    = self::validate_hours( $saved['age_hours'] ?? 72 );
		$allowed  = array_keys( self::available_statuses() );
		$selected = isset( $saved['statuses'] ) && is_array( $saved['statuses'] ) ? $saved['statuses'] : array( 'wc-processing', 'wc-on-hold', 'wc-pending' );
		$selected = array_values( array_intersect( array_filter( $selected, 'is_string' ), $allowed ) );
		if ( ! $selected || count( $selected ) > self::MAX_STATUSES ) {
			$selected = array_values( array_intersect( array( 'wc-processing', 'wc-on-hold', 'wc-pending' ), $allowed ) );
		}
		return array(
			'age_hours' => is_wp_error( $hours ) ? 72 : $hours,
			'statuses'  => $selected,
		);
	}

	/**
	 * Validate and save preferences for the current user only.
	 *
	 * @param int|string $hours Hours.
	 * @param array      $statuses Statuses.
	 * @return mixed Validated result or error; hook callbacks return no value.
	 * @throws RuntimeException When authorization or data integrity cannot be confirmed.
	 */
	public static function save( $hours, $statuses ) {
		if ( ! self::can_view() ) {
			throw new RuntimeException( 'Unauthorized operational preference write.' );
		}
		$hours = self::validate_hours( $hours );
		if ( is_wp_error( $hours ) ) {
			return $hours;
		}
		if ( ! is_array( $statuses ) || ! $statuses || count( $statuses ) > self::MAX_STATUSES ) {
			return self::invalid_statuses();
		}
		$allowed = self::available_statuses();
		foreach ( $statuses as $status ) {
			if ( ! is_string( $status ) || ! isset( $allowed[ $status ] ) ) {
				return self::invalid_statuses();
			}
		}
		$preferences = array(
			'age_hours' => $hours,
			'statuses'  => array_values( array_unique( $statuses ) ),
		);
		// Site-specific option, always for the current user. Never accept a target user ID.
		$updated = update_user_option( get_current_user_id(), self::USER_OPTION, $preferences, false );
		if ( ! $updated && get_user_option( self::USER_OPTION ) !== $preferences ) {
			return new WP_Error( 'adwc_preferences_failed', __( 'Preferences could not be saved. Try again.', 'advanced-dashboard-for-woocommerce' ), array( 'status' => 503 ) );
		}
		return $preferences;
	}

	/**
	 * Read and validate data for the current authorized user.
	 *
	 * @return mixed Validated result or error; hook callbacks return no value.
	 * @throws RuntimeException When authorization or data integrity cannot be confirmed.
	 */
	public static function read() {
		if ( ! self::can_view() || ! ADWC_Plugin::available() ) {
			throw new RuntimeException( 'Unauthorized operational data access.' );
		}
		$preferences = self::preferences();
		$now         = time();
		$cutoff      = $now - $preferences['age_hours'] * HOUR_IN_SECONDS;
		$orders      = $preferences['statuses'] ? wc_get_orders(
			array(
				'type'         => 'shop_order',
				'status'       => $preferences['statuses'],
				'date_created' => '<' . $cutoff,
				'orderby'      => 'date',
				'order'        => 'ASC',
				'limit'        => self::MAX_ORDERS,
				'paginate'     => false,
				'return'       => 'objects',
			)
		) : array();
		$rows        = array();
		foreach ( array_slice( $orders, 0, self::MAX_ORDERS ) as $order ) {
			if ( ! current_user_can( 'edit_shop_order', $order->get_id() ) ) {
				continue;
			}
			$created = $order->get_date_created();
			if ( ! $created || $created->getTimestamp() >= $cutoff ) {
				continue;
			}
			$rows[] = array(
				'number'      => (string) $order->get_order_number(),
				'status'      => wc_get_order_status_name( $order->get_status() ),
				'created'     => wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $created->getTimestamp(), wp_timezone() ),
				'created_iso' => wp_date( DATE_ATOM, $created->getTimestamp(), wp_timezone() ),
				'age_seconds' => max( 0, $now - $created->getTimestamp() ),
				/* translators: %s: localized elapsed time, for example "3 days". */
				'age'         => sprintf( __( '%s old', 'advanced-dashboard-for-woocommerce' ), human_time_diff( $created->getTimestamp(), $now ) ),
				'edit_url'    => $order->get_edit_order_url(),
			);
		}
		return array(
			'preferences' => $preferences,
			'counts'      => array(
				'processing' => (int) wc_orders_count( 'processing' ),
				'on_hold'    => (int) wc_orders_count( 'on-hold' ),
				'pending'    => (int) wc_orders_count( 'pending' ),
			),
			'rows'        => $rows,
			'limit'       => self::MAX_ORDERS,
			'timezone'    => wp_timezone_string(),
			/* translators: %d: age threshold in hours. */
			'description' => sprintf( __( 'Up to 10 oldest orders created more than %d hours ago, in your selected statuses.', 'advanced-dashboard-for-woocommerce' ), $preferences['age_hours'] ),
		);
	}

	/**
	 * Validate a whole-hour threshold within the supported limits.
	 *
	 * @param int|string $hours Hours.
	 * @return mixed Validated result or error; hook callbacks return no value.
	 */
	private static function validate_hours( $hours ) {
		if ( ( ! is_int( $hours ) && ( ! is_string( $hours ) || ! preg_match( '/^[1-9][0-9]{0,3}$/D', $hours ) ) ) || (int) $hours < 1 || (int) $hours > self::MAX_HOURS ) {
			return new WP_Error( 'adwc_age_invalid', __( 'Enter a whole number of hours between 1 and 8760.', 'advanced-dashboard-for-woocommerce' ), array( 'status' => 400 ) );
		}
		return (int) $hours;
	}

	/**
	 * Build a queue-status validation error.
	 *
	 * @return mixed Validated result or error; hook callbacks return no value.
	 */
	private static function invalid_statuses() {
		return new WP_Error( 'adwc_statuses_invalid', __( 'Select between 1 and 20 available queue statuses.', 'advanced-dashboard-for-woocommerce' ), array( 'status' => 400 ) );
	}
}
