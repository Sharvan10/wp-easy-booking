<?php
/**
 * Generates blueprint.json for WordPress Playground.
 *
 * The demo seeder (bin/demo-data.php) is embedded into a runPHP step so the
 * blueprint is self-contained. Run after changing the seeder:
 *
 *   php bin/build-blueprint.php
 *
 * @package EasyBooking
 */

// phpcs:disable WordPress.WP.AlternativeFunctions -- CLI build script, runs outside WordPress.

$easy_booking_root = dirname( __DIR__ );
$easy_booking_seed = file_get_contents( $easy_booking_root . '/bin/demo-data.php' );

$easy_booking_blueprint = array(
	'$schema'           => 'https://playground.wordpress.net/blueprint-schema.json',
	'meta'              => array(
		'title'       => 'Easy Appointment Booking demo',
		'description' => 'Installs Easy Appointment Booking from GitHub, creates a booking page and adds sample services and bookings.',
		'author'      => 'Sharvan10',
		'categories'  => array( 'Booking', 'Appointments' ),
	),
	'landingPage'       => '/book/',
	'preferredVersions' => array(
		'php' => '8.2',
		'wp'  => 'latest',
	),
	'features'          => array(
		'networking' => true,
	),
	'login'             => true,
	'steps'             => array(
		array(
			'step'       => 'installPlugin',
			'pluginData' => array(
				'resource' => 'git:directory',
				'url'      => 'https://github.com/Sharvan10/wp-easy-booking',
				'ref'      => 'main',
				'refType'  => 'branch',
			),
			'options'    => array(
				'activate'         => true,
				'targetFolderName' => 'easy-booking',
			),
		),
		array(
			'step' => 'runPHP',
			'code' => $easy_booking_seed,
		),
	),
);

file_put_contents(
	$easy_booking_root . '/blueprint.json',
	json_encode( $easy_booking_blueprint, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n"
);

echo "blueprint.json updated.\n";
