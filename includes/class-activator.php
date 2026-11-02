<?php
/**
 * Activation / database installation routines.
 *
 * @package EasyBooking
 */

namespace EasyBooking;

defined( 'ABSPATH' ) || exit;

/**
 * Class Activator
 */
final class Activator {

	/**
	 * Option that stores the installed database schema version.
	 *
	 * @var string
	 */
	const DB_VERSION_OPTION = 'easy_booking_db_version';

	/**
	 * Plugin activation callback.
	 *
	 * @return void
	 */
	public static function activate() {
		self::create_table();

		// Seed default settings only on first install, never overwrite.
		if ( false === get_option( Settings::OPTION_NAME ) ) {
			add_option( Settings::OPTION_NAME, Settings::defaults() );
		}
	}

	/**
	 * Re-run the schema installer if the stored DB version is outdated.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		if ( get_option( self::DB_VERSION_OPTION ) !== EASY_BOOKING_DB_VERSION ) {
			self::activate();
		}
	}

	/**
	 * Create or update the bookings table using dbDelta().
	 *
	 * Note: dbDelta is whitespace sensitive (two spaces after PRIMARY KEY,
	 * one field per line), so formatting below is intentional.
	 *
	 * @return void
	 */
	public static function create_table() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table           = Booking_Repository::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			service_id varchar(64) NOT NULL DEFAULT '',
			service_name varchar(191) NOT NULL DEFAULT '',
			service_price decimal(10,2) NOT NULL DEFAULT '0.00',
			duration smallint(5) unsigned NOT NULL DEFAULT 30,
			customer_name varchar(191) NOT NULL DEFAULT '',
			customer_email varchar(191) NOT NULL DEFAULT '',
			customer_phone varchar(50) NOT NULL DEFAULT '',
			booking_date date NOT NULL,
			start_time time NOT NULL,
			end_time time NOT NULL,
			notes text NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY booking_date (booking_date),
			KEY status (status),
			KEY customer_email (customer_email)
		) {$charset_collate};";

		dbDelta( $sql );

		update_option( self::DB_VERSION_OPTION, EASY_BOOKING_DB_VERSION );
	}
}
