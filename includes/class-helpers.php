<?php
/**
 * Small, stateless helper functions.
 *
 * @package EasyBooking
 */

namespace EasyBooking;

use DateTimeImmutable;

defined( 'ABSPATH' ) || exit;

/**
 * Class Helpers
 */
final class Helpers {

	/**
	 * Strictly validate a Y-m-d date string.
	 *
	 * @param mixed $date Value to check.
	 * @return bool
	 */
	public static function is_valid_date( $date ) {
		if ( ! is_string( $date ) || ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m ) ) {
			return false;
		}

		return checkdate( (int) $m[2], (int) $m[3], (int) $m[1] );
	}

	/**
	 * Convert "HH:MM" or "HH:MM:SS" to minutes since midnight.
	 *
	 * @param string $time Time string.
	 * @return int
	 */
	public static function time_to_minutes( $time ) {
		$parts = array_map( 'intval', explode( ':', (string) $time ) );

		return ( isset( $parts[0] ) ? $parts[0] : 0 ) * 60 + ( isset( $parts[1] ) ? $parts[1] : 0 );
	}

	/**
	 * Convert minutes since midnight to "HH:MM:SS".
	 *
	 * @param int $minutes Minutes.
	 * @return string
	 */
	public static function minutes_to_time( $minutes ) {
		$minutes = max( 0, (int) $minutes );

		return sprintf( '%02d:%02d:00', intdiv( $minutes, 60 ), $minutes % 60 );
	}

	/**
	 * Current date/time in the site's timezone.
	 *
	 * @return DateTimeImmutable
	 */
	public static function now() {
		return current_datetime();
	}

	/**
	 * Build a DateTimeImmutable in the site timezone from a date and time.
	 *
	 * @param string $date Y-m-d.
	 * @param string $time H:i or H:i:s.
	 * @return DateTimeImmutable|false
	 */
	public static function to_datetime( $date, $time = '00:00:00' ) {
		$time = substr( (string) $time, 0, 5 );

		return DateTimeImmutable::createFromFormat( 'Y-m-d H:i', $date . ' ' . $time, wp_timezone() );
	}

	/**
	 * Format a Y-m-d date using the site's date format.
	 *
	 * @param string $date Y-m-d.
	 * @return string
	 */
	public static function format_date( $date ) {
		$dt = self::to_datetime( $date );

		return $dt ? wp_date( get_option( 'date_format' ), $dt->getTimestamp() ) : (string) $date;
	}

	/**
	 * Format a time using the site's time format.
	 *
	 * @param string $time H:i or H:i:s.
	 * @return string
	 */
	public static function format_time( $time ) {
		$dt = self::to_datetime( '2000-01-01', $time );

		return $dt ? wp_date( get_option( 'time_format' ), $dt->getTimestamp() ) : (string) $time;
	}

	/**
	 * Human readable "start – end" range for a booking.
	 *
	 * @param object $booking Booking row.
	 * @return string
	 */
	public static function format_time_range( $booking ) {
		return self::format_time( $booking->start_time ) . ' – ' . self::format_time( $booking->end_time );
	}

	/**
	 * Admin URL of the single booking screen.
	 *
	 * @param int $booking_id Booking ID.
	 * @return string
	 */
	public static function booking_admin_url( $booking_id ) {
		return add_query_arg(
			array(
				'page'       => 'easy-booking',
				'action'     => 'edit',
				'booking_id' => absint( $booking_id ),
			),
			admin_url( 'admin.php' )
		);
	}
}
