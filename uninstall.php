<?php
/**
 * Uninstall routine: removes all plugin data.
 *
 * Runs only when the plugin is deleted from the Plugins screen.
 *
 * @package EasyBooking
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Remove the plugin's table, options and transients for the current site.
 *
 * @return void
 */
function easy_booking_uninstall_site() {
	global $wpdb;

	$table = $wpdb->prefix . 'easy_bookings';

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Dropping our own table; name is not user input.
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" );

	delete_option( 'easy_booking_settings' );
	delete_option( 'easy_booking_db_version' );

	// Leftover slot locks and rate-limit transients.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bulk cleanup of our own rows.
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( 'easy_booking_lock_' ) . '%',
			$wpdb->esc_like( '_transient_easy_booking_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_easy_booking_' ) . '%'
		)
	);
}

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids' ) ) as $easy_booking_site_id ) {
		switch_to_blog( $easy_booking_site_id );
		easy_booking_uninstall_site();
		restore_current_blog();
	}
} else {
	easy_booking_uninstall_site();
}

// Per-user "bookings per page" screen option.
delete_metadata( 'user', 0, 'easy_booking_per_page', '', true );
