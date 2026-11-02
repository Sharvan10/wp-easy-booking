<?php
/**
 * Main plugin orchestrator.
 *
 * @package EasyBooking
 */

namespace EasyBooking;

use EasyBooking\Admin\Admin;
use EasyBooking\Frontend\Ajax;
use EasyBooking\Frontend\Shortcode;

defined( 'ABSPATH' ) || exit;

/**
 * Class Plugin
 *
 * Boots every component of the plugin. Implemented as a singleton so the
 * component graph is only ever built once per request.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Booking data access layer.
	 *
	 * @var Booking_Repository
	 */
	private $repository;

	/**
	 * Slot availability calculator.
	 *
	 * @var Availability
	 */
	private $availability;

	/**
	 * Email sender.
	 *
	 * @var Emails
	 */
	private $emails;

	/**
	 * Get (and lazily create) the plugin instance.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->boot();
		}

		return self::$instance;
	}

	/**
	 * Private constructor - use Plugin::instance().
	 */
	private function __construct() {}

	/**
	 * Wire up services and hooks.
	 *
	 * @return void
	 */
	private function boot() {
		$this->repository   = new Booking_Repository();
		$this->availability = new Availability( $this->repository );
		$this->emails       = new Emails( $this->repository );

		add_action( 'init', array( $this, 'load_textdomain' ) );

		// Run DB upgrades when the plugin is updated without re-activation.
		add_action( 'admin_init', array( Activator::class, 'maybe_upgrade' ) );

		// Send emails whenever a booking is created or its status changes.
		add_action( 'easy_booking_created', array( $this->emails, 'on_booking_created' ) );
		add_action( 'easy_booking_status_changed', array( $this->emails, 'on_status_changed' ), 10, 3 );

		( new Shortcode( $this->availability ) )->register();
		( new Ajax( $this->repository, $this->availability ) )->register();

		if ( is_admin() ) {
			( new Admin( $this->repository ) )->register();
		}
	}

	/**
	 * Load translations from the /languages folder.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'easy-booking', false, dirname( plugin_basename( EASY_BOOKING_FILE ) ) . '/languages' );
	}

	/**
	 * Booking repository accessor (useful for extensions).
	 *
	 * @return Booking_Repository
	 */
	public function repository() {
		return $this->repository;
	}

	/**
	 * Availability accessor (useful for extensions).
	 *
	 * @return Availability
	 */
	public function availability() {
		return $this->availability;
	}

	/**
	 * Get the capability required to manage bookings.
	 *
	 * @return string
	 */
	public static function capability() {
		/**
		 * Filters the capability required to manage bookings and settings.
		 *
		 * @param string $capability Default 'manage_options'.
		 */
		return (string) apply_filters( 'easy_booking_capability', 'manage_options' );
	}
}
