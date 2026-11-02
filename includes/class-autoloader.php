<?php
/**
 * PSR-4 style autoloader that maps namespaced classes to
 * WordPress-style file names (class-{name}.php).
 *
 * @package EasyBooking
 */

namespace EasyBooking;

defined( 'ABSPATH' ) || exit;

/**
 * Class Autoloader
 *
 * Namespace to folder mapping:
 *   EasyBooking\Foo_Bar           => includes/class-foo-bar.php
 *   EasyBooking\Admin\Foo_Bar     => admin/class-foo-bar.php
 *   EasyBooking\Frontend\Foo_Bar  => public/class-foo-bar.php
 */
final class Autoloader {

	/**
	 * Root namespace handled by this autoloader.
	 *
	 * @var string
	 */
	const ROOT_NAMESPACE = 'EasyBooking\\';

	/**
	 * Sub-namespace to directory map (relative to the plugin root).
	 *
	 * @var array<string, string>
	 */
	const DIRECTORIES = array(
		''         => 'includes/',
		'Admin'    => 'admin/',
		'Frontend' => 'public/',
	);

	/**
	 * Register the autoloader with SPL.
	 *
	 * @return void
	 */
	public static function register() {
		spl_autoload_register( array( __CLASS__, 'autoload' ) );
	}

	/**
	 * Load the file for a given class, if it belongs to this plugin.
	 *
	 * @param string $class_name Fully-qualified class name.
	 * @return void
	 */
	public static function autoload( $class_name ) {
		if ( 0 !== strpos( $class_name, self::ROOT_NAMESPACE ) ) {
			return;
		}

		$relative = substr( $class_name, strlen( self::ROOT_NAMESPACE ) );
		$parts    = explode( '\\', $relative );
		$class    = array_pop( $parts );
		$sub_ns   = implode( '\\', $parts );

		if ( ! isset( self::DIRECTORIES[ $sub_ns ] ) ) {
			return;
		}

		$file = EASY_BOOKING_PATH . self::DIRECTORIES[ $sub_ns ] . 'class-' . strtolower( str_replace( '_', '-', $class ) ) . '.php';

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
}
