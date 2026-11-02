<?php
/**
 * Booking status definitions.
 *
 * @package EasyBooking
 */

namespace EasyBooking;

defined( 'ABSPATH' ) || exit;

/**
 * Class Status
 *
 * Central place for the allowed booking statuses and their labels.
 */
final class Status {

	const PENDING   = 'pending';
	const CONFIRMED = 'confirmed';
	const CANCELLED = 'cancelled';

	/**
	 * All statuses with their translated labels.
	 *
	 * @return array<string, string>
	 */
	public static function all() {
		return array(
			self::PENDING   => __( 'Pending', 'easy-booking' ),
			self::CONFIRMED => __( 'Confirmed', 'easy-booking' ),
			self::CANCELLED => __( 'Cancelled', 'easy-booking' ),
		);
	}

	/**
	 * Whether a status key is valid.
	 *
	 * @param string $status Status key.
	 * @return bool
	 */
	public static function is_valid( $status ) {
		return array_key_exists( (string) $status, self::all() );
	}

	/**
	 * Translated label for a status key.
	 *
	 * @param string $status Status key.
	 * @return string
	 */
	public static function label( $status ) {
		$all = self::all();

		return isset( $all[ $status ] ) ? $all[ $status ] : ucfirst( (string) $status );
	}

	/**
	 * Statuses that occupy a time slot (i.e. block other bookings).
	 *
	 * @return string[]
	 */
	public static function blocking() {
		return array( self::PENDING, self::CONFIRMED );
	}
}
