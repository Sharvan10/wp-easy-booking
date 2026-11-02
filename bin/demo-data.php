<?php
/**
 * Demo data seeder for WordPress Playground (and local testing).
 *
 * Creates a "Book an Appointment" page with the [easy_booking] shortcode,
 * configures 3 sample services and inserts 5 sample bookings on upcoming
 * working days.
 *
 * Usage:
 *   - Embedded into blueprint.json by bin/build-blueprint.php.
 *   - Or locally: wp eval-file bin/demo-data.php
 *
 * @package EasyBooking
 */

// When run by Playground's runPHP step, WordPress is not loaded yet.
if ( ! defined( 'ABSPATH' ) ) {
	require_once '/wordpress/wp-load.php';
}

use EasyBooking\Activator;
use EasyBooking\Helpers;
use EasyBooking\Plugin;
use EasyBooking\Settings;
use EasyBooking\Status;

// Fail loudly (instead of with an obscure fatal) if the plugin did not load.
if ( ! class_exists( Plugin::class ) ) {
	throw new RuntimeException( 'Easy Appointment Booking is not active; demo data was not created.' );
}

// Make sure the bookings table exists even if the activation hook was skipped.
Activator::activate();

// 1. Settings: 3 services, Mon-Sat 09:00-18:00, 30 minute slots.
$easy_booking_settings = array_merge(
	Settings::all(),
	array(
		'services'     => array(
			array(
				'id'       => 'haircut',
				'name'     => 'Haircut & Styling',
				'duration' => 45,
				'price'    => 35,
			),
			array(
				'id'       => 'consultation',
				'name'     => 'Free Consultation',
				'duration' => 30,
				'price'    => 0,
			),
			array(
				'id'       => 'coloring',
				'name'     => 'Hair Coloring',
				'duration' => 90,
				'price'    => 80,
			),
		),
		'working_days' => array( 1, 2, 3, 4, 5, 6 ),
		'start_time'   => '09:00',
		'end_time'     => '18:00',
		'slot_length'  => 30,
	)
);
update_option( Settings::OPTION_NAME, $easy_booking_settings );
Settings::flush_cache();

// 2. Pretty permalinks so the demo page lives at /book/.
if ( '' === get_option( 'permalink_structure' ) ) {
	global $wp_rewrite;
	$wp_rewrite->set_permalink_structure( '/%postname%/' );
	flush_rewrite_rules();
}

// 3. Booking page (created once, so the seeder can safely run again).
if ( ! get_page_by_path( 'book' ) ) {
	wp_insert_post(
		array(
			'post_title'   => 'Book an Appointment',
			'post_name'    => 'book',
			'post_content' => "<!-- wp:paragraph -->\n<p>Pick a service and a time that suits you. You will receive a confirmation email right away.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:shortcode -->\n[easy_booking]\n<!-- /wp:shortcode -->",
			'post_status'  => 'publish',
			'post_type'    => 'page',
		)
	);
}

// 4. Five sample bookings on the next working days.
$easy_booking_customers = array(
	array( 'Emma Johnson', 'emma@example.com', '+1 555 0101', 'haircut', '10:00', Status::CONFIRMED, 'First visit, prefers a short style.' ),
	array( 'Liam Smith', 'liam@example.com', '+1 555 0102', 'consultation', '11:30', Status::PENDING, '' ),
	array( 'Olivia Brown', 'olivia@example.com', '+1 555 0103', 'coloring', '14:00', Status::CONFIRMED, 'Allergic to ammonia-based products.' ),
	array( 'Noah Davis', 'noah@example.com', '+1 555 0104', 'haircut', '09:30', Status::PENDING, '' ),
	array( 'Ava Wilson', 'ava@example.com', '+1 555 0105', 'consultation', '16:00', Status::CANCELLED, 'Had to reschedule.' ),
);

$easy_booking_repository = Plugin::instance()->repository();

// Only seed bookings into an empty table.
if ( $easy_booking_repository->count() > 0 ) {
	return;
}

$easy_booking_day        = Helpers::now()->modify( '-1 day' );

foreach ( $easy_booking_customers as $easy_booking_row ) {
	// One booking per working day, starting today (or the next working day).
	do {
		$easy_booking_day = $easy_booking_day->modify( '+1 day' );
	} while ( ! in_array( (int) $easy_booking_day->format( 'N' ), $easy_booking_settings['working_days'], true ) );

	$easy_booking_service = Settings::find_service( $easy_booking_row[3] );
	$easy_booking_start   = Helpers::time_to_minutes( $easy_booking_row[4] );

	$easy_booking_repository->create(
		array(
			'service_id'     => $easy_booking_service['id'],
			'service_name'   => $easy_booking_service['name'],
			'service_price'  => $easy_booking_service['price'],
			'duration'       => $easy_booking_service['duration'],
			'customer_name'  => $easy_booking_row[0],
			'customer_email' => $easy_booking_row[1],
			'customer_phone' => $easy_booking_row[2],
			'booking_date'   => $easy_booking_day->format( 'Y-m-d' ),
			'start_time'     => Helpers::minutes_to_time( $easy_booking_start ),
			'end_time'       => Helpers::minutes_to_time( $easy_booking_start + (int) $easy_booking_service['duration'] ),
			'notes'          => $easy_booking_row[6],
			'status'         => $easy_booking_row[5],
		)
	);
}
