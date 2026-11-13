<?php
/**
 * Store-local calendar ranges, independent of the PHP/server/browser timezone.
 *
 * @package AdvancedDashboardForWooCommerce
 */

defined( 'ABSPATH' ) || exit;

/** Resolves bounded store-local calendar ranges. */
final class ADWC_Date_Range {
	const MAX_DAYS = 90;

	/**
	 * Resolve a bounded range of store-local calendar days.
	 *
	 * @param string                 $preset Preset.
	 * @param string                 $from From.
	 * @param string                 $to To.
	 * @param DateTimeImmutable|null $now Now.
	 * @return mixed Validated result or error; hook callbacks return no value.
	 */
	public static function resolve( $preset, $from = '', $to = '', $now = null ) {
		$today = ( $now ? $now : current_datetime() )->setTimezone( wp_timezone() )->setTime( 0, 0, 0 );
		if ( ! is_string( $preset ) ) {
			return self::invalid();
		}
		switch ( $preset ) {
			case '7':
			case '30':
			case '90':
				$start = $today->modify( '-' . ( (int) $preset - 1 ) . ' days' );
				$end   = $today;
				break;
			case 'month':
				$start = $today->modify( 'first day of this month' );
				$end   = $today;
				break;
			case 'custom':
				$start = self::parse( $from );
				$end   = self::parse( $to );
				if ( ! $start || ! $end ) {
					return self::invalid();
				}
				break;
			default:
				return self::invalid();
		}
		if ( $start > $end ) {
			return new WP_Error( 'adwc_dates_reversed', __( 'The start date must be on or before the end date.', 'advanced-dashboard-for-woocommerce' ), array( 'status' => 400 ) );
		}
		if ( $end > $today ) {
			return new WP_Error( 'adwc_dates_future', __( 'Choose dates up to today in the store timezone.', 'advanced-dashboard-for-woocommerce' ), array( 'status' => 400 ) );
		}
		if ( (int) $start->diff( $end )->days + 1 > self::MAX_DAYS ) {
			return new WP_Error( 'adwc_dates_limit', __( 'Choose a range of at most 90 days, including both dates.', 'advanced-dashboard-for-woocommerce' ), array( 'status' => 400 ) );
		}
		$end = $end->setTime( 23, 59, 59 );
		return array(
			'start' => $start,
			'end'   => $end,
		);
	}

	/**
	 * Parse a strict ISO calendar date in the store timezone.
	 *
	 * @param string $value Value.
	 * @return mixed Validated result or error; hook callbacks return no value.
	 */
	private static function parse( $value ) {
		if ( ! is_string( $value ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/D', $value ) || $value < '1970-01-01' ) {
			return false;
		}
		$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $value, wp_timezone() );
		return $date && $date->format( 'Y-m-d' ) === $value ? $date : false;
	}

	/**
	 * Build a date validation error.
	 *
	 * @return mixed Validated result or error; hook callbacks return no value.
	 */
	private static function invalid() {
		return new WP_Error( 'adwc_dates_invalid', __( 'Select a valid range and enter both dates in YYYY-MM-DD format.', 'advanced-dashboard-for-woocommerce' ), array( 'status' => 400 ) );
	}
}
