<?php
/**
 * Settings model: defaults, typed getters and sanitization.
 *
 * @package EasyBooking
 */

namespace EasyBooking;

defined( 'ABSPATH' ) || exit;

/**
 * Class Settings
 *
 * All plugin settings are stored in a single autoloaded option array.
 */
final class Settings {

	/**
	 * Option name used to store every setting.
	 *
	 * @var string
	 */
	const OPTION_NAME = 'easy_booking_settings';

	/**
	 * Allowed slot lengths (minutes).
	 *
	 * @var int[]
	 */
	const SLOT_LENGTHS = array( 10, 15, 20, 30, 45, 60, 90, 120 );

	/**
	 * Request-level cache of the merged settings.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * Default settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults() {
		return array(
			'services'         => array(
				array(
					'id'       => 'consultation',
					'name'     => __( 'Consultation', 'easy-booking' ),
					'duration' => 30,
					'price'    => 0,
				),
			),
			'working_days'     => array( 1, 2, 3, 4, 5 ), // ISO-8601: 1 = Monday ... 7 = Sunday.
			'start_time'       => '09:00',
			'end_time'         => '17:00',
			'slot_length'      => 30,
			'booking_window'   => 60,
			'currency'         => '$',
			'admin_email'      => get_option( 'admin_email' ),
			'customer_subject' => __( 'Your booking request at {site_name}', 'easy-booking' ),
			'customer_body'    => __( "Hi {customer_name},\n\nThank you for your booking. Here are the details:\n\nService: {service}\nDate: {date}\nTime: {time}\nStatus: {status}\n\nWe will contact you if anything changes.\n\n{site_name}", 'easy-booking' ),
			'admin_subject'    => __( 'New booking #{booking_id} from {customer_name}', 'easy-booking' ),
			'admin_body'       => __( "A new booking has been received.\n\nName: {customer_name}\nEmail: {customer_email}\nPhone: {customer_phone}\nService: {service}\nDate: {date}\nTime: {time}\nNotes: {notes}\n\nManage it here: {admin_url}", 'easy-booking' ),
			'status_subject'   => __( 'Your booking is now {status}', 'easy-booking' ),
			'status_body'      => __( "Hi {customer_name},\n\nThe status of your booking has been updated.\n\nService: {service}\nDate: {date}\nTime: {time}\nNew status: {status}\n\n{site_name}", 'easy-booking' ),
		);
	}

	/**
	 * Get all settings merged with defaults.
	 *
	 * @return array<string, mixed>
	 */
	public static function all() {
		if ( null === self::$cache ) {
			$saved       = get_option( self::OPTION_NAME, array() );
			self::$cache = wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
		}

		return self::$cache;
	}

	/**
	 * Get a single setting.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $fallback Value returned when the key does not exist.
	 * @return mixed
	 */
	public static function get( $key, $fallback = null ) {
		$all = self::all();

		return array_key_exists( $key, $all ) ? $all[ $key ] : $fallback;
	}

	/**
	 * Clear the request cache (called after the option is updated).
	 *
	 * @return void
	 */
	public static function flush_cache() {
		self::$cache = null;
	}

	/**
	 * Configured services.
	 *
	 * @return array<int, array{id:string,name:string,duration:int,price:float}>
	 */
	public static function services() {
		$services = self::get( 'services', array() );

		return is_array( $services ) ? array_values( $services ) : array();
	}

	/**
	 * Find a service by its ID.
	 *
	 * @param string $service_id Service ID.
	 * @return array|null
	 */
	public static function find_service( $service_id ) {
		foreach ( self::services() as $service ) {
			if ( isset( $service['id'] ) && (string) $service['id'] === (string) $service_id ) {
				return $service;
			}
		}

		return null;
	}

	/**
	 * Format a price using the configured currency symbol.
	 *
	 * @param float $price Price.
	 * @return string
	 */
	public static function format_price( $price ) {
		return self::get( 'currency', '$' ) . number_format_i18n( (float) $price, 2 );
	}

	/**
	 * Sanitize the full settings array submitted from the settings page.
	 *
	 * Registered as the `sanitize_callback` of register_setting().
	 *
	 * @param mixed $input Raw input.
	 * @return array<string, mixed>
	 */
	public static function sanitize( $input ) {
		$input    = is_array( $input ) ? $input : array();
		$defaults = self::defaults();
		$clean    = array();

		// Services repeater.
		$clean['services'] = array();
		$used_ids          = array();
		$raw_services      = isset( $input['services'] ) && is_array( $input['services'] ) ? $input['services'] : array();

		foreach ( $raw_services as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$name = isset( $row['name'] ) ? sanitize_text_field( wp_unslash( $row['name'] ) ) : '';
			if ( '' === $name ) {
				continue; // Empty rows are treated as removed.
			}

			$id = isset( $row['id'] ) ? sanitize_key( $row['id'] ) : '';
			if ( '' === $id || in_array( $id, $used_ids, true ) ) {
				$id = self::generate_service_id( $name, $used_ids );
			}
			$used_ids[] = $id;

			$duration = isset( $row['duration'] ) ? absint( $row['duration'] ) : 30;
			$price    = isset( $row['price'] ) ? (float) str_replace( ',', '.', sanitize_text_field( wp_unslash( $row['price'] ) ) ) : 0.0;

			$clean['services'][] = array(
				'id'       => $id,
				'name'     => $name,
				'duration' => min( 480, max( 5, $duration ) ),
				'price'    => round( max( 0, $price ), 2 ),
			);
		}

		if ( empty( $clean['services'] ) ) {
			add_settings_error( self::OPTION_NAME, 'no_services', __( 'Please add at least one service so customers can book.', 'easy-booking' ), 'warning' );
		}

		// Working days (ISO-8601 numbers 1-7).
		$days                  = isset( $input['working_days'] ) && is_array( $input['working_days'] ) ? array_map( 'absint', $input['working_days'] ) : array();
		$clean['working_days'] = array_values( array_intersect( range( 1, 7 ), $days ) );

		// Working hours.
		$start = isset( $input['start_time'] ) ? self::sanitize_time( $input['start_time'] ) : '';
		$end   = isset( $input['end_time'] ) ? self::sanitize_time( $input['end_time'] ) : '';

		if ( '' === $start || '' === $end || $start >= $end ) {
			add_settings_error( self::OPTION_NAME, 'invalid_hours', __( 'Working hours are invalid: the end time must be after the start time. Previous values were kept.', 'easy-booking' ) );
			$start = self::get( 'start_time', $defaults['start_time'] );
			$end   = self::get( 'end_time', $defaults['end_time'] );
		}

		$clean['start_time'] = $start;
		$clean['end_time']   = $end;

		// Slot length.
		$slot                 = isset( $input['slot_length'] ) ? absint( $input['slot_length'] ) : $defaults['slot_length'];
		$clean['slot_length'] = in_array( $slot, self::SLOT_LENGTHS, true ) ? $slot : $defaults['slot_length'];

		// Booking window (how many days ahead customers may book).
		$window                  = isset( $input['booking_window'] ) ? absint( $input['booking_window'] ) : $defaults['booking_window'];
		$clean['booking_window'] = min( 365, max( 1, $window ) );

		// Currency symbol.
		$currency          = isset( $input['currency'] ) ? sanitize_text_field( wp_unslash( $input['currency'] ) ) : $defaults['currency'];
		$clean['currency'] = mb_substr( $currency, 0, 5 );

		// Admin notification email.
		$email = isset( $input['admin_email'] ) ? sanitize_email( wp_unslash( $input['admin_email'] ) ) : '';
		if ( ! is_email( $email ) ) {
			add_settings_error( self::OPTION_NAME, 'invalid_email', __( 'The notification email address is invalid. The site admin email will be used instead.', 'easy-booking' ) );
			$email = get_option( 'admin_email' );
		}
		$clean['admin_email'] = $email;

		// Email templates.
		foreach ( array( 'customer', 'admin', 'status' ) as $template ) {
			$subject_key = $template . '_subject';
			$body_key    = $template . '_body';

			$subject = isset( $input[ $subject_key ] ) && is_string( $input[ $subject_key ] ) ? sanitize_text_field( wp_unslash( $input[ $subject_key ] ) ) : '';
			$body    = isset( $input[ $body_key ] ) && is_string( $input[ $body_key ] ) ? sanitize_textarea_field( wp_unslash( $input[ $body_key ] ) ) : '';

			// Fall back to the default template when a field is left empty.
			$clean[ $subject_key ] = '' !== $subject ? $subject : $defaults[ $subject_key ];
			$clean[ $body_key ]    = '' !== $body ? $body : $defaults[ $body_key ];
		}

		self::flush_cache();

		return $clean;
	}

	/**
	 * Validate an "HH:MM" time string.
	 *
	 * @param mixed $value Raw value.
	 * @return string Normalised "HH:MM" or empty string when invalid.
	 */
	public static function sanitize_time( $value ) {
		$value = is_string( $value ) ? trim( $value ) : '';

		if ( ! preg_match( '/^([01]\d|2[0-3]):([0-5]\d)(?::[0-5]\d)?$/', $value, $m ) ) {
			return '';
		}

		return $m[1] . ':' . $m[2];
	}

	/**
	 * Build a unique, URL-safe service ID from its name.
	 *
	 * @param string   $name     Service name.
	 * @param string[] $used_ids IDs already in use.
	 * @return string
	 */
	private static function generate_service_id( $name, array $used_ids ) {
		$base = sanitize_key( sanitize_title( $name ) );
		$base = '' !== $base ? substr( $base, 0, 40 ) : 'service';
		$id   = $base;
		$i    = 2;

		while ( in_array( $id, $used_ids, true ) ) {
			$id = $base . '-' . $i;
			++$i;
		}

		return $id;
	}
}
