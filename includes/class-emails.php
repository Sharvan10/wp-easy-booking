<?php
/**
 * Transactional emails.
 *
 * @package EasyBooking
 */

namespace EasyBooking;

defined( 'ABSPATH' ) || exit;

/**
 * Class Emails
 *
 * Sends plain-text emails built from the templates configured in settings.
 * Templates support {placeholders}; see Emails::placeholders().
 */
class Emails {

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
	 * New booking: confirmation to customer + notification to admin.
	 *
	 * @param int $booking_id Booking ID.
	 * @return void
	 */
	public function on_booking_created( $booking_id ) {
		$booking = $this->repository->find( $booking_id );
		if ( ! $booking ) {
			return;
		}

		$this->send_template( $booking->customer_email, 'customer', $booking );

		$admin_email = Settings::get( 'admin_email', get_option( 'admin_email' ) );
		if ( is_email( $admin_email ) ) {
			// Let the admin reply straight to the customer.
			$this->send_template( $admin_email, 'admin', $booking, array( 'Reply-To: ' . $booking->customer_email ) );
		}
	}

	/**
	 * Status change: notify the customer.
	 *
	 * @param int    $booking_id Booking ID.
	 * @param string $new_status New status.
	 * @param string $old_status Old status.
	 * @return void
	 */
	public function on_status_changed( $booking_id, $new_status, $old_status ) {
		/**
		 * Filters whether the customer gets an email for this status change.
		 *
		 * @param bool   $send       Default true.
		 * @param int    $booking_id Booking ID.
		 * @param string $new_status New status.
		 * @param string $old_status Old status.
		 */
		if ( ! apply_filters( 'easy_booking_send_status_email', true, $booking_id, $new_status, $old_status ) ) {
			return;
		}

		$booking = $this->repository->find( $booking_id );
		if ( $booking ) {
			$this->send_template( $booking->customer_email, 'status', $booking );
		}
	}

	/**
	 * Render a template and send it.
	 *
	 * @param string   $to       Recipient.
	 * @param string   $template Template key: customer|admin|status.
	 * @param object   $booking  Booking row.
	 * @param string[] $headers  Extra headers.
	 * @return bool
	 */
	public function send_template( $to, $template, $booking, array $headers = array() ) {
		$to = sanitize_email( $to );
		if ( ! is_email( $to ) ) {
			return false;
		}

		$replacements = $this->placeholders( $booking );

		// Subjects must be single line to avoid header injection.
		$subject = str_replace( array( "\r", "\n" ), ' ', strtr( (string) Settings::get( $template . '_subject', '' ), $replacements ) );
		$body    = strtr( (string) Settings::get( $template . '_body', '' ), $replacements );

		$headers[] = 'Content-Type: text/plain; charset=UTF-8';

		/**
		 * Filters an outgoing email before it is sent.
		 *
		 * @param array  $email    { to, subject, body, headers }.
		 * @param string $template Template key.
		 * @param object $booking  Booking row.
		 */
		$email = apply_filters(
			'easy_booking_email',
			array(
				'to'      => $to,
				'subject' => $subject,
				'body'    => $body,
				'headers' => $headers,
			),
			$template,
			$booking
		);

		return wp_mail( $email['to'], $email['subject'], $email['body'], $email['headers'] );
	}

	/**
	 * Placeholder => value map for a booking.
	 *
	 * Values are plain text (emails are sent as text/plain), with line breaks
	 * stripped from single-line fields.
	 *
	 * @param object $booking Booking row.
	 * @return array<string, string>
	 */
	public function placeholders( $booking ) {
		$single_line = static function ( $value ) {
			return trim( str_replace( array( "\r", "\n" ), ' ', (string) $value ) );
		};

		return array(
			'{booking_id}'     => (string) absint( $booking->id ),
			'{customer_name}'  => $single_line( $booking->customer_name ),
			'{customer_email}' => $single_line( $booking->customer_email ),
			'{customer_phone}' => $single_line( $booking->customer_phone ),
			'{service}'        => $single_line( $booking->service_name ),
			'{price}'          => Settings::format_price( $booking->service_price ),
			'{date}'           => Helpers::format_date( $booking->booking_date ),
			'{time}'           => Helpers::format_time_range( $booking ),
			'{status}'         => Status::label( $booking->status ),
			'{notes}'          => '' !== (string) $booking->notes ? (string) $booking->notes : '-',
			'{site_name}'      => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			'{site_url}'       => home_url( '/' ),
			'{admin_url}'      => Helpers::booking_admin_url( $booking->id ),
		);
	}

	/**
	 * List of supported placeholders (for the settings screen).
	 *
	 * @return string[]
	 */
	public static function placeholder_names() {
		return array( '{booking_id}', '{customer_name}', '{customer_email}', '{customer_phone}', '{service}', '{price}', '{date}', '{time}', '{status}', '{notes}', '{site_name}', '{site_url}', '{admin_url}' );
	}
}
