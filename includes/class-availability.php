<?php
/**
 * Time slot / availability calculation.
 *
 * @package EasyBooking
 */

namespace EasyBooking;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Class Availability
 *
 * Generates bookable slots for a date and service based on working days,
 * working hours, slot length, service duration and existing bookings.
 */
class Availability {

	/**
	 * Booking repository.
	 *
	 * @var Booking_Repository
	 */
	private $repository;

	/**
	 * Constructor.
	 *
	 * @param Booking_Repository $repository Booking repository.
	 */
	public function __construct( Booking_Repository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * First bookable date (today) in Y-m-d.
	 *
	 * @return string
	 */
	public function min_date() {
		return Helpers::now()->format( 'Y-m-d' );
	}

	/**
	 * Last bookable date in Y-m-d, based on the booking window setting.
	 *
	 * @return string
	 */
	public function max_date() {
		$days = max( 1, (int) Settings::get( 'booking_window', 60 ) );

		return Helpers::now()->modify( '+' . $days . ' days' )->format( 'Y-m-d' );
	}

	/**
	 * Whether a date is a working day.
	 *
	 * @param string $date Y-m-d.
	 * @return bool
	 */
	public function is_working_day( $date ) {
		$dt = Helpers::to_datetime( $date );
		if ( ! $dt ) {
			return false;
		}

		$working_days = array_map( 'intval', (array) Settings::get( 'working_days', array() ) );

		return in_array( (int) $dt->format( 'N' ), $working_days, true );
	}

	/**
	 * Validate that a date may be booked by customers.
	 *
	 * @param string $date Y-m-d.
	 * @return true|WP_Error
	 */
	public function validate_date( $date ) {
		if ( ! Helpers::is_valid_date( $date ) ) {
			return new WP_Error( 'easy_booking_invalid_date', __( 'Please choose a valid date.', 'easy-booking' ) );
		}

		if ( $date < $this->min_date() ) {
			return new WP_Error( 'easy_booking_past_date', __( 'You cannot book a date in the past.', 'easy-booking' ) );
		}

		if ( $date > $this->max_date() ) {
			return new WP_Error( 'easy_booking_far_date', __( 'That date is too far in the future.', 'easy-booking' ) );
		}

		if ( ! $this->is_working_day( $date ) ) {
			return new WP_Error( 'easy_booking_closed', __( 'We are closed on that day. Please pick another date.', 'easy-booking' ) );
		}

		return true;
	}

	/**
	 * Get every available slot for a service on a date.
	 *
	 * @param string $date     Y-m-d.
	 * @param array  $service  Service definition (needs 'duration').
	 * @param int    $exclude_id Booking ID to ignore (admin edits).
	 * @return array<int, array{start:string,end:string,label:string}>
	 */
	public function get_available_slots( $date, array $service, $exclude_id = 0 ) {
		if ( true !== $this->validate_date( $date ) ) {
			return array();
		}

		$open     = Helpers::time_to_minutes( Settings::get( 'start_time', '09:00' ) );
		$close    = Helpers::time_to_minutes( Settings::get( 'end_time', '17:00' ) );
		$step     = max( 5, (int) Settings::get( 'slot_length', 30 ) );
		$duration = max( 5, (int) $service['duration'] );

		// Do not offer slots that already started today.
		$earliest = $open;
		if ( $date === $this->min_date() ) {
			$now      = Helpers::now();
			$earliest = max( $open, (int) $now->format( 'G' ) * 60 + (int) $now->format( 'i' ) + 1 );
		}

		// Pre-compute busy ranges once instead of querying per slot.
		$busy = array();
		foreach ( $this->repository->get_blocking_bookings( $date, $exclude_id ) as $booking ) {
			$busy[] = array( Helpers::time_to_minutes( $booking->start_time ), Helpers::time_to_minutes( $booking->end_time ) );
		}

		$slots = array();
		for ( $start = $open; $start + $duration <= $close; $start += $step ) {
			if ( $start < $earliest ) {
				continue;
			}

			$end = $start + $duration;

			foreach ( $busy as $range ) {
				if ( $start < $range[1] && $range[0] < $end ) {
					continue 2; // Overlaps an existing booking - hide this slot.
				}
			}

			$start_time = Helpers::minutes_to_time( $start );
			$slots[]    = array(
				'start' => substr( $start_time, 0, 5 ),
				'end'   => substr( Helpers::minutes_to_time( $end ), 0, 5 ),
				'label' => Helpers::format_time( $start_time ),
			);
		}

		/**
		 * Filters the available slots for a date and service.
		 *
		 * @param array  $slots   Slots.
		 * @param string $date    Y-m-d.
		 * @param array  $service Service definition.
		 */
		return (array) apply_filters( 'easy_booking_available_slots', $slots, $date, $service );
	}

	/**
	 * Whether a specific start time is currently offered for a service/date.
	 *
	 * @param string $date       Y-m-d.
	 * @param string $start_time H:i.
	 * @param array  $service    Service definition.
	 * @return bool
	 */
	public function is_slot_available( $date, $start_time, array $service ) {
		foreach ( $this->get_available_slots( $date, $service ) as $slot ) {
			if ( $slot['start'] === $start_time ) {
				return true;
			}
		}

		return false;
	}
}
