<?php
/**
 * [easy_booking] shortcode.
 *
 * @package EasyBooking
 */

namespace EasyBooking\Frontend;

use EasyBooking\Availability;
use EasyBooking\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Class Shortcode
 */
class Shortcode {

	/**
	 * Shortcode tag.
	 *
	 * @var string
	 */
	const TAG = 'easy_booking';

	/**
	 * Availability service.
	 *
	 * @var Availability
	 */
	private $availability;

	/**
	 * Constructor.
	 *
	 * @param Availability $availability Availability service.
	 */
	public function __construct( Availability $availability ) {
		$this->availability = $availability;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_shortcode( self::TAG, array( $this, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
	}

	/**
	 * Register (and, when the shortcode is present, enqueue) assets.
	 *
	 * @return void
	 */
	public function register_assets() {
		wp_register_style( 'easy-booking', EASY_BOOKING_URL . 'assets/css/frontend.css', array(), EASY_BOOKING_VERSION );
		wp_register_script( 'easy-booking', EASY_BOOKING_URL . 'assets/js/frontend.js', array(), EASY_BOOKING_VERSION, true );

		wp_localize_script(
			'easy-booking',
			'easyBooking',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( Ajax::NONCE_ACTION ),
				'workingDays' => array_map( 'intval', (array) Settings::get( 'working_days', array() ) ),
				'i18n'        => array(
					'loading'      => __( 'Loading available times…', 'easy-booking' ),
					'chooseFirst'  => __( 'Choose a service and a date to see available times.', 'easy-booking' ),
					'noSlots'      => __( 'No times are available on this date. Please try another day.', 'easy-booking' ),
					'closedDay'    => __( 'We are closed on that day. Please pick another date.', 'easy-booking' ),
					'submitting'   => __( 'Sending…', 'easy-booking' ),
					'genericError' => __( 'Something went wrong. Please try again.', 'easy-booking' ),
				),
			)
		);

		// Enqueue early (in <head>) when we know the current post uses the shortcode.
		$post = get_post();
		if ( is_singular() && $post && has_shortcode( $post->post_content, self::TAG ) ) {
			wp_enqueue_style( 'easy-booking' );
			wp_enqueue_script( 'easy-booking' );
		}
	}

	/**
	 * Render the booking form.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'title'   => __( 'Book an appointment', 'easy-booking' ),
				'service' => '',
			),
			$atts,
			self::TAG
		);

		// Fallback for widgets, page builders, etc.
		wp_enqueue_style( 'easy-booking' );
		wp_enqueue_script( 'easy-booking' );

		$services = Settings::services();
		$selected = sanitize_key( $atts['service'] );
		$min_date = $this->availability->min_date();
		$max_date = $this->availability->max_date();
		$title    = $atts['title'];

		// Unique prefix so several forms can live on the same page.
		$uid = wp_unique_id( 'easy-booking-' );

		ob_start();
		include EASY_BOOKING_PATH . 'public/views/booking-form.php';

		return (string) ob_get_clean();
	}
}
