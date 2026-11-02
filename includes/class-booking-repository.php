<?php
/**
 * Data access layer for the bookings table.
 *
 * Every query that contains variable data goes through $wpdb->prepare().
 *
 * @package EasyBooking
 */

namespace EasyBooking;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Class Booking_Repository
 */
class Booking_Repository {

	/**
	 * Columns that may be used in ORDER BY clauses (whitelist).
	 *
	 * @var string[]
	 */
	const SORTABLE_COLUMNS = array( 'id', 'customer_name', 'service_name', 'booking_date', 'status', 'created_at' );

	/**
	 * How long (seconds) a slot lock is considered valid before it is treated as stale.
	 *
	 * @var int
	 */
	const LOCK_TTL = 15;

	/**
	 * Fully-qualified bookings table name.
	 *
	 * @return string
	 */
	public static function table_name() {
		global $wpdb;

		return $wpdb->prefix . 'easy_bookings';
	}

	/**
	 * Fetch a single booking.
	 *
	 * @param int $id Booking ID.
	 * @return object|null
	 */
	public function find( $id ) {
		global $wpdb;

		$table = self::table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table, always fresh; table name is not user input.
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $id ) ) );
	}

	/**
	 * Query bookings with filtering, sorting and pagination.
	 *
	 * @param array $args {
	 *     Optional. Query arguments.
	 *
	 *     @type string $search    Search term (name, email, phone).
	 *     @type string $status    Status key or '' for all.
	 *     @type string $date_from Y-m-d lower bound (inclusive).
	 *     @type string $date_to   Y-m-d upper bound (inclusive).
	 *     @type string $orderby   Column to sort by (whitelisted).
	 *     @type string $order     ASC|DESC.
	 *     @type int    $per_page  Items per page; 0 = no limit.
	 *     @type int    $page      1-based page number.
	 * }
	 * @return object[]
	 */
	public function query( array $args = array() ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'orderby'  => 'booking_date',
				'order'    => 'DESC',
				'per_page' => 20,
				'page'     => 1,
			)
		);

		list( $where_sql, $params ) = $this->build_where( $args );

		$orderby = in_array( $args['orderby'], self::SORTABLE_COLUMNS, true ) ? $args['orderby'] : 'booking_date';
		$order   = 'ASC' === strtoupper( (string) $args['order'] ) ? 'ASC' : 'DESC';
		$table   = self::table_name();

		// Secondary sort keeps the order stable and puts time slots in sequence.
		$sql = "SELECT * FROM {$table} {$where_sql} ORDER BY {$orderby} {$order}, start_time {$order}, id {$order}";

		$per_page = absint( $args['per_page'] );
		if ( $per_page > 0 ) {
			$offset   = ( max( 1, absint( $args['page'] ) ) - 1 ) * $per_page;
			$sql     .= ' LIMIT %d OFFSET %d';
			$params[] = $per_page;
			$params[] = $offset;
		}

		if ( $params ) {
			$sql = $wpdb->prepare( $sql, $params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Built from whitelisted fragments and placeholders.
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Prepared above.
		return $wpdb->get_results( $sql );
	}

	/**
	 * Count bookings matching the given filters.
	 *
	 * @param array $args Same filter keys as query().
	 * @return int
	 */
	public function count( array $args = array() ) {
		global $wpdb;

		list( $where_sql, $params ) = $this->build_where( $args );

		$table = self::table_name();
		$sql   = "SELECT COUNT(*) FROM {$table} {$where_sql}";

		if ( $params ) {
			$sql = $wpdb->prepare( $sql, $params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Built from whitelisted fragments and placeholders.
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Prepared above.
		return (int) $wpdb->get_var( $sql );
	}

	/**
	 * Count bookings grouped by status.
	 *
	 * @return array<string, int>
	 */
	public function count_by_status() {
		global $wpdb;

		$table = self::table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- No user input.
		$rows   = $wpdb->get_results( "SELECT status, COUNT(*) AS total FROM {$table} GROUP BY status" );
		$counts = array_fill_keys( array_keys( Status::all() ), 0 );

		foreach ( (array) $rows as $row ) {
			$counts[ $row->status ] = (int) $row->total;
		}

		return $counts;
	}

	/**
	 * Get bookings for a date that block a time slot (pending or confirmed).
	 *
	 * @param string $date       Y-m-d.
	 * @param int    $exclude_id Booking ID to ignore (when editing).
	 * @return object[]
	 */
	public function get_blocking_bookings( $date, $exclude_id = 0 ) {
		global $wpdb;

		$table = self::table_name();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Availability must never be cached; table name is safe.
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, start_time, end_time FROM {$table}
				WHERE booking_date = %s AND status IN (%s, %s) AND id <> %d
				ORDER BY start_time ASC",
				$date,
				Status::PENDING,
				Status::CONFIRMED,
				absint( $exclude_id )
			)
		);
		// phpcs:enable
	}

	/**
	 * Whether a time range overlaps an existing blocking booking.
	 *
	 * Two ranges [a1, a2) and [b1, b2) overlap when a1 < b2 AND b1 < a2.
	 *
	 * @param string $date       Y-m-d.
	 * @param string $start_time H:i:s.
	 * @param string $end_time   H:i:s.
	 * @param int    $exclude_id Booking ID to ignore (when editing).
	 * @return bool
	 */
	public function has_conflict( $date, $start_time, $end_time, $exclude_id = 0 ) {
		global $wpdb;

		$table = self::table_name();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Must hit the DB; table name is safe.
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table}
				WHERE booking_date = %s
				AND status IN (%s, %s)
				AND id <> %d
				AND start_time < %s
				AND end_time > %s",
				$date,
				Status::PENDING,
				Status::CONFIRMED,
				absint( $exclude_id ),
				$end_time,
				$start_time
			)
		);
		// phpcs:enable

		return (int) $count > 0;
	}

	/**
	 * Insert a new booking, guarding against double booking.
	 *
	 * A short-lived, per-date lock (an atomic INSERT IGNORE into the options
	 * table) serialises concurrent requests, and the overlap check runs inside
	 * that lock, so two customers can never grab the same slot at the same moment.
	 *
	 * @param array $data Sanitized booking data.
	 * @return int|WP_Error New booking ID or error.
	 */
	public function create( array $data ) {
		global $wpdb;

		if ( ! $this->acquire_lock( $data['booking_date'] ) ) {
			return new WP_Error( 'easy_booking_busy', __( 'The booking system is busy. Please try again in a moment.', 'easy-booking' ) );
		}

		try {
			if ( $this->has_conflict( $data['booking_date'], $data['start_time'], $data['end_time'] ) ) {
				return new WP_Error( 'easy_booking_slot_taken', __( 'Sorry, that time slot has just been booked. Please choose another one.', 'easy-booking' ) );
			}

			$now = current_time( 'mysql' );
			$row = array(
				'service_id'     => $data['service_id'],
				'service_name'   => $data['service_name'],
				'service_price'  => $data['service_price'],
				'duration'       => $data['duration'],
				'customer_name'  => $data['customer_name'],
				'customer_email' => $data['customer_email'],
				'customer_phone' => $data['customer_phone'],
				'booking_date'   => $data['booking_date'],
				'start_time'     => $data['start_time'],
				'end_time'       => $data['end_time'],
				'notes'          => $data['notes'],
				'status'         => isset( $data['status'] ) && Status::is_valid( $data['status'] ) ? $data['status'] : Status::PENDING,
				'created_at'     => $now,
				'updated_at'     => $now,
			);

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table insert.
			$inserted = $wpdb->insert(
				self::table_name(),
				$row,
				array( '%s', '%s', '%f', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
			);

			if ( false === $inserted ) {
				return new WP_Error( 'easy_booking_db_error', __( 'Could not save your booking. Please try again.', 'easy-booking' ) );
			}

			return (int) $wpdb->insert_id;
		} finally {
			$this->release_lock( $data['booking_date'] );
		}
	}

	/**
	 * Update editable fields of a booking.
	 *
	 * Fires `easy_booking_status_changed` when the status changes.
	 *
	 * @param int   $id   Booking ID.
	 * @param array $data Sanitized column => value pairs.
	 * @return true|WP_Error
	 */
	public function update( $id, array $data ) {
		global $wpdb;

		$booking = $this->find( $id );
		if ( ! $booking ) {
			return new WP_Error( 'easy_booking_not_found', __( 'Booking not found.', 'easy-booking' ) );
		}

		$formats = array(
			'customer_name'  => '%s',
			'customer_email' => '%s',
			'customer_phone' => '%s',
			'booking_date'   => '%s',
			'start_time'     => '%s',
			'end_time'       => '%s',
			'notes'          => '%s',
			'status'         => '%s',
		);

		// Only allow known columns through.
		$data = array_intersect_key( $data, $formats );

		if ( isset( $data['status'] ) && ! Status::is_valid( $data['status'] ) ) {
			return new WP_Error( 'easy_booking_invalid_status', __( 'Invalid status.', 'easy-booking' ) );
		}

		$date       = isset( $data['booking_date'] ) ? $data['booking_date'] : $booking->booking_date;
		$start      = isset( $data['start_time'] ) ? $data['start_time'] : $booking->start_time;
		$end        = isset( $data['end_time'] ) ? $data['end_time'] : $booking->end_time;
		$new_status = isset( $data['status'] ) ? $data['status'] : $booking->status;

		// Re-check for overlaps when the booking will occupy a slot.
		if ( in_array( $new_status, Status::blocking(), true ) && $this->has_conflict( $date, $start, $end, $booking->id ) ) {
			return new WP_Error( 'easy_booking_slot_taken', __( 'This time overlaps with another active booking.', 'easy-booking' ) );
		}

		$data['updated_at']    = current_time( 'mysql' );
		$formats['updated_at'] = '%s';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table update.
		$updated = $wpdb->update(
			self::table_name(),
			$data,
			array( 'id' => (int) $booking->id ),
			array_values( array_intersect_key( $formats, $data ) ),
			array( '%d' )
		);

		if ( false === $updated ) {
			return new WP_Error( 'easy_booking_db_error', __( 'Could not update the booking.', 'easy-booking' ) );
		}

		if ( $new_status !== $booking->status ) {
			/**
			 * Fires after a booking's status has changed.
			 *
			 * @param int    $booking_id Booking ID.
			 * @param string $new_status New status.
			 * @param string $old_status Previous status.
			 */
			do_action( 'easy_booking_status_changed', (int) $booking->id, $new_status, $booking->status );
		}

		return true;
	}

	/**
	 * Change the status of a booking.
	 *
	 * @param int    $id     Booking ID.
	 * @param string $status New status.
	 * @return true|WP_Error
	 */
	public function set_status( $id, $status ) {
		return $this->update( $id, array( 'status' => $status ) );
	}

	/**
	 * Permanently delete a booking.
	 *
	 * @param int $id Booking ID.
	 * @return bool
	 */
	public function delete( $id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table delete.
		return (bool) $wpdb->delete( self::table_name(), array( 'id' => absint( $id ) ), array( '%d' ) );
	}

	/**
	 * Build a WHERE clause and its prepare() params from filter args.
	 *
	 * @param array $args Filter args.
	 * @return array{0:string,1:array}
	 */
	private function build_where( array $args ) {
		global $wpdb;

		$where  = array();
		$params = array();

		if ( ! empty( $args['status'] ) && Status::is_valid( $args['status'] ) ) {
			$where[]  = 'status = %s';
			$params[] = $args['status'];
		}

		if ( ! empty( $args['exclude_status'] ) && Status::is_valid( $args['exclude_status'] ) ) {
			$where[]  = 'status <> %s';
			$params[] = $args['exclude_status'];
		}

		if ( ! empty( $args['date_from'] ) && Helpers::is_valid_date( $args['date_from'] ) ) {
			$where[]  = 'booking_date >= %s';
			$params[] = $args['date_from'];
		}

		if ( ! empty( $args['date_to'] ) && Helpers::is_valid_date( $args['date_to'] ) ) {
			$where[]  = 'booking_date <= %s';
			$params[] = $args['date_to'];
		}

		if ( ! empty( $args['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]  = '(customer_name LIKE %s OR customer_email LIKE %s OR customer_phone LIKE %s OR service_name LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		$where_sql = $where ? 'WHERE ' . implode( ' AND ', $where ) : '';

		return array( $where_sql, $params );
	}

	/**
	 * Acquire a short-lived lock for a booking date.
	 *
	 * Uses an atomic `INSERT IGNORE` on the options table's UNIQUE option_name
	 * column, so only one concurrent request can hold the lock. (add_option()
	 * is not suitable here because it performs an upsert.) The object cache is
	 * bypassed on purpose. Stale locks left by crashed requests are removed.
	 *
	 * @param string $date Y-m-d.
	 * @return bool
	 */
	private function acquire_lock( $date ) {
		global $wpdb;

		$name = $this->lock_name( $date );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Locking must bypass caches.
		for ( $attempt = 0; $attempt < 10; $attempt++ ) {
			$inserted = $wpdb->query(
				$wpdb->prepare(
					"INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
					$name,
					(string) time()
				)
			);

			if ( $inserted ) {
				return true;
			}

			$locked_at = (int) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $name ) );

			if ( $locked_at && ( time() - $locked_at ) > self::LOCK_TTL ) {
				$this->release_lock( $date );
				continue;
			}

			usleep( 200000 ); // Wait 0.2s before retrying.
		}
		// phpcs:enable

		return false;
	}

	/**
	 * Release the lock for a booking date.
	 *
	 * @param string $date Y-m-d.
	 * @return void
	 */
	private function release_lock( $date ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Locking must bypass caches.
		$wpdb->delete( $wpdb->options, array( 'option_name' => $this->lock_name( $date ) ), array( '%s' ) );
	}

	/**
	 * Option name used for a date lock.
	 *
	 * @param string $date Y-m-d.
	 * @return string
	 */
	private function lock_name( $date ) {
		return 'easy_booking_lock_' . preg_replace( '/[^0-9]/', '', $date );
	}
}
