<?php
/**
 * Plugin Name:       Easy Appointment Booking
 * Plugin URI:        https://github.com/Sharvan10/wp-easy-booking
 * Description:       A lightweight, secure appointment booking system. Add the [easy_booking] shortcode to any page to accept bookings with real-time slot availability.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Sharvan Kumar
 * Author URI:        https://github.com/Sharvan10
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       easy-booking
 * Domain Path:       /languages
 *
 * @package EasyBooking
 */

defined( 'ABSPATH' ) || exit;

define( 'EASY_BOOKING_VERSION', '1.0.0' );
define( 'EASY_BOOKING_DB_VERSION', '1.0.0' );
define( 'EASY_BOOKING_FILE', __FILE__ );
define( 'EASY_BOOKING_PATH', plugin_dir_path( __FILE__ ) );
define( 'EASY_BOOKING_URL', plugin_dir_url( __FILE__ ) );

require_once EASY_BOOKING_PATH . 'includes/class-autoloader.php';

\EasyBooking\Autoloader::register();

register_activation_hook( __FILE__, array( \EasyBooking\Activator::class, 'activate' ) );

add_action( 'plugins_loaded', array( \EasyBooking\Plugin::class, 'instance' ) );
