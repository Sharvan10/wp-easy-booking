<?php
/**
 * Public AJAX endpoints (available slots + booking submission).
 *
 * @package EasyBooking
 */

namespace EasyBooking\Frontend;

use EasyBooking\Availability;
use EasyBooking\Booking_Repository;
use EasyBooking\Helpers;
use EasyBooking\Settings;
use EasyBooking\Status;

defined( 'ABSPATH' ) || exit;

/**
 * Class Ajax
 */
class Ajax {

	/**
	 * Nonce action shared by all public requests.
	 *
	 * @var string
	 */
	const NONCE_ACTION = 'easy_booking_public';

	/**
	 * Max booking submissions per IP within RATE_LIMIT_WINDOW.
	 *
	 * @var int
	 */
	const RATE_LIMIT = 5;

	/**
	 * Rate limit window in seconds.
	 *
	 * @var int
	 */
	const RATE_LIMIT_WINDOW = 600;

	/**
	 * Booking repository.
	 *
	 * @var Booking_Repository
	 */
	private $repository;

	/**
	 * Availability service.
	 *
	 * @var Availability
	 */
	private $availability;

	/**
	 * Constructor.
	 *
	 * @param Booking_Repository $repository   Booking repository.
	 * @param Availability       $availability Availability service.
	 */
	public function __construct( Booking_Repository $repository, Availability $availability ) {
		$this->repository   = $repository;
		$this->availability = $availability;
	}

	/**
	 * Register AJAX hooks for logged-in and anonymous visitors.
	 *
	 * @return void
	 */
	public function register() {
		$actions = array(
			'easy_booking_get_slots' => 'get_slots',
			'easy_booking_submit'    => 'submit',
		);

		foreach ( $actions as $action => $method ) {
			add_action( 'wp_ajax_' . $action, array( $this, $method ) );
			add_action( 'wp_ajax_nopriv_' . $action, array( $this, $method ) );
		}
	}

	/**
	 * Return the available slots for a service and date.
	 *
	 * @return void
	 */
	public function get_slots() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		$service_id = isset( $_POST['service_id'] ) ? sanitize_key( wp_unslash( $_POST['service_id'] ) ) : '';
		$date       = isset( $_POST['booking_date'] ) ? sanitize_text_field( wp_unslash( $_POST['booking_date'] ) ) : '';

		$service = Settings::find_service( $service_id );
		if ( ! $service ) {
			wp_send_json_error( array( 'message' => __( 'Please choose a service.', 'easy-booking' ) ), 400 );
		}

		$valid = $this->availability->validate_date( $date );
		if ( is_wp_error( $valid ) ) {
			wp_send_json_error( array( 'message' => $valid->get_error_message() ), 400 );
		}

		wp_send_json_success( array( 'slots' => $this->availability->get_available_slots( $date, $service ) ) );
	}

	/**
	 * Validate and store a booking.
	 *
	 * @return void
	 */
	public function submit() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		// Honeypot: pretend success so bots learn nothing.
		if ( ! empty( $_POST['eb_website'] ) ) {
			wp_send_json_success( array( 'message' => __( 'Thank you! Your booking request has been received.', 'easy-booking' ) ) );
		}

		if ( $this->is_rate_limited() ) {
			wp_send_json_error( array( 'message' => __( 'Too many booking attempts. Please try again later.', 'easy-booking' ) ), 429 );
		}

		$raw = array(
			'customer_name'  => isset( $_POST['customer_name'] ) ? sanitize_text_field( wp_unslash( $_POST['customer_name'] ) ) : '',
			'customer_email' => isset( $_POST['customer_email'] ) ? sanitize_email( wp_unslash( $_POST['customer_email'] ) ) : '',
			'customer_phone' => isset( $_POST['customer_phone'] ) ? self::sanitize_phone( wp_unslash( $_POST['customer_phone'] ) ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized by sanitize_phone().
			'service_id'     => isset( $_POST['service_id'] ) ? sanitize_key( wp_unslash( $_POST['service_id'] ) ) : '',
			'booking_date'   => isset( $_POST['booking_date'] ) ? sanitize_text_field( wp_unslash( $_POST['booking_date'] ) ) : '',
			'start_time'     => isset( $_POST['start_time'] ) ? Settings::sanitize_time( sanitize_text_field( wp_unslash( $_POST['start_time'] ) ) ) : '',
			'notes'          => isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '',
		);

		$errors  = array();
		$service = Settings::find_service( $raw['service_id'] );

		if ( '' === $raw['customer_name'] ) {
			$errors['customer_name'] = __( 'Please enter your name.', 'easy-booking' );
		} elseif ( mb_strlen( $raw['customer_name'] ) > 191 ) {
			$errors['customer_name'] = __( 'Your name is too long.', 'easy-booking' );
		}

		if ( ! is_email( $raw['customer_email'] ) ) {
			$errors['customer_email'] = __( 'Please enter a valid email address.', 'easy-booking' );
		}

		if ( strlen( preg_replace( '/\D/', '', $raw['customer_phone'] ) ) < 6 ) {
			$errors['customer_phone'] = __( 'Please enter a valid phone number.', 'easy-booking' );
		}

		if ( ! $service ) {
			$errors['service_id'] = __( 'Please choose a service.', 'easy-booking' );
		}

		$date_check = $this->availability->validate_date( $raw['booking_date'] );
		if ( is_wp_error( $date_check ) ) {
			$errors['booking_date'] = $date_check->get_error_message();
		}

		if ( '' === $raw['start_time'] ) {
			$errors['start_time'] = __( 'Please choose a time slot.', 'easy-booking' );
		} elseif ( $service && ! isset( $errors['booking_date'] ) && ! $this->availability->is_slot_available( $raw['booking_date'], $raw['start_time'], $service ) ) {
			$errors['start_time'] = __( 'Sorry, that time is no longer available. Please choose another one.', 'easy-booking' );
		}

		if ( mb_strlen( $raw['notes'] ) > 1000 ) {
			$errors['notes'] = __( 'Notes must be 1000 characters or fewer.', 'easy-booking' );
		}

		/**
		 * Filters booking validation errors, allowing extra rules.
		 *
		 * @param array $errors Field => message.
		 * @param array $raw    Sanitized submission.
		 */
		$errors = (array) apply_filters( 'easy_booking_validation_errors', $errors, $raw );

		if ( $errors ) {
			wp_send_json_error(
				array(
					'message' => __( 'Please correct the highlighted fields.', 'easy-booking' ),
					'errors'  => $errors,
				),
				422
			);
		}

		$start_minutes = Helpers::time_to_minutes( $raw['start_time'] );

		$booking_id = $this->repository->create(
			array(
				'service_id'     => $service['id'],
				'service_name'   => $service['name'],
				'service_price'  => (float) $service['price'],
				'duration'       => (int) $service['duration'],
				'customer_name'  => $raw['customer_name'],
				'customer_email' => $raw['customer_email'],
				'customer_phone' => $raw['customer_phone'],
				'booking_date'   => $raw['booking_date'],
				'start_time'     => Helpers::minutes_to_time( $start_minutes ),
				'end_time'       => Helpers::minutes_to_time( $start_minutes + (int) $service['duration'] ),
				'notes'          => $raw['notes'],
				'status'         => Status::PENDING,
			)
		);

		if ( is_wp_error( $booking_id ) ) {
			$status = 'easy_booking_slot_taken' === $booking_id->get_error_code() ? 409 : 500;
			wp_send_json_error(
				array(
					'message' => $booking_id->get_error_message(),
					'errors'  => 409 === $status ? array( 'start_time' => $booking_id->get_error_message() ) : array(),
				),
				$status
			);
		}

		/**
		 * Fires after a new booking has been stored.
		 *
		 * @param int $booking_id Booking ID.
		 */
		do_action( 'easy_booking_created', $booking_id );

		$this->record_submission();

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: 1: service name, 2: date, 3: time. */
					__( 'Thank you! Your booking for %1$s on %2$s at %3$s has been received. A confirmation email is on its way.', 'easy-booking' ),
					$service['name'],
					Helpers::format_date( $raw['booking_date'] ),
					Helpers::format_time( $raw['start_time'] )
				),
			)
		);
	}

	/**
	 * Keep digits, spaces and common phone punctuation only.
	 *
	 * @param mixed $phone Raw phone.
	 * @return string
	 */
	public static function sanitize_phone( $phone ) {
		$phone = sanitize_text_field( is_string( $phone ) ? $phone : '' );

		return substr( trim( preg_replace( '/[^0-9+\-().\s]/', '', $phone ) ), 0, 50 );
	}

	/**
	 * Whether the visitor has reached the booking limit for the current window.
	 *
	 * @return bool
	 */
	private function is_rate_limited() {
		$key = $this->rate_limit_key();

		/**
		 * Filters the number of bookings an IP may submit per window (0 disables the limit).
		 *
		 * @param int $limit Default 5.
		 */
		$limit = (int) apply_filters( 'easy_booking_rate_limit', self::RATE_LIMIT );

		return $key && $limit > 0 && (int) get_transient( $key ) >= $limit;
	}

	/**
	 * Count a successful submission towards the visitor's limit.
	 *
	 * @return void
	 */
	private function record_submission() {
		$key = $this->rate_limit_key();

		if ( $key ) {
			set_transient( $key, (int) get_transient( $key ) + 1, self::RATE_LIMIT_WINDOW );
		}
	}

	/**
	 * Transient key for the current visitor.
	 *
	 * The IP is hashed with a site salt so no personal data is stored.
	 *
	 * @return string Empty string when the IP is unknown.
	 */
	private function rate_limit_key() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		return '' === $ip ? '' : 'easy_booking_rl_' . md5( $ip . wp_salt( 'nonce' ) );
	}
}
