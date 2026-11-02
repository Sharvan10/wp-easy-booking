<?php
/**
 * CSV export of bookings.
 *
 * @package EasyBooking
 */

namespace EasyBooking\Admin;

use EasyBooking\Booking_Repository;
use EasyBooking\Plugin;
use EasyBooking\Status;

defined( 'ABSPATH' ) || exit;

/**
 * Class Csv_Exporter
 */
class Csv_Exporter {

	/**
	 * Action name used with admin-post.php.
	 *
	 * @var string
	 */
	const ACTION = 'easy_booking_export';

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
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'export' ) );
	}

	/**
	 * Build a nonce-protected export URL that keeps the current filters.
	 *
	 * @param array $filters Current list filters.
	 * @return string
	 */
	public static function url( array $filters = array() ) {
		$args = array_filter(
			array(
				'action'    => self::ACTION,
				'status'    => isset( $filters['status'] ) ? $filters['status'] : '',
				'date_from' => isset( $filters['date_from'] ) ? $filters['date_from'] : '',
				'date_to'   => isset( $filters['date_to'] ) ? $filters['date_to'] : '',
				's'         => isset( $filters['search'] ) ? $filters['search'] : '',
			)
		);

		return wp_nonce_url( add_query_arg( $args, admin_url( 'admin-post.php' ) ), self::ACTION );
	}

	/**
	 * Stream the CSV file to the browser.
	 *
	 * @return void
	 */
	public function export() {
		if ( ! current_user_can( Plugin::capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to export bookings.', 'easy-booking' ), 403 );
		}

		check_admin_referer( self::ACTION );

		$filters             = Bookings_List_Table::current_filters();
		$filters['orderby']  = 'booking_date';
		$filters['order']    = 'ASC';
		$filters['per_page'] = 0; // No limit.

		$bookings = $this->repository->query( $filters );
		$filename = 'bookings-' . gmdate( 'Y-m-d' ) . '.csv';

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		$out = fopen( 'php://output', 'w' );

		// UTF-8 BOM so Excel detects the encoding correctly.
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Streaming to php://output.

		fputcsv(
			$out,
			array(
				__( 'ID', 'easy-booking' ),
				__( 'Customer', 'easy-booking' ),
				__( 'Email', 'easy-booking' ),
				__( 'Phone', 'easy-booking' ),
				__( 'Service', 'easy-booking' ),
				__( 'Price', 'easy-booking' ),
				__( 'Duration (min)', 'easy-booking' ),
				__( 'Date', 'easy-booking' ),
				__( 'Start', 'easy-booking' ),
				__( 'End', 'easy-booking' ),
				__( 'Status', 'easy-booking' ),
				__( 'Notes', 'easy-booking' ),
				__( 'Created', 'easy-booking' ),
			),
			',',
			'"',
			'\\'
		);

		foreach ( $bookings as $booking ) {
			fputcsv(
				$out,
				array_map(
					array( __CLASS__, 'escape_cell' ),
					array(
						$booking->id,
						$booking->customer_name,
						$booking->customer_email,
						$booking->customer_phone,
						$booking->service_name,
						$booking->service_price,
						$booking->duration,
						$booking->booking_date,
						substr( $booking->start_time, 0, 5 ),
						substr( $booking->end_time, 0, 5 ),
						Status::label( $booking->status ),
						$booking->notes,
						$booking->created_at,
					)
				),
				',',
				'"',
				'\\'
			);
		}

		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Streaming to php://output.
		exit;
	}

	/**
	 * Neutralise spreadsheet formula injection (CSV injection).
	 *
	 * Cells beginning with =, +, -, @, tab or carriage return are prefixed
	 * with a single quote so spreadsheet apps treat them as text.
	 *
	 * @param mixed $value Cell value.
	 * @return string
	 */
	public static function escape_cell( $value ) {
		$value = (string) $value;

		if ( '' !== $value && in_array( $value[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ) {
			$value = "'" . $value;
		}

		return $value;
	}
}
