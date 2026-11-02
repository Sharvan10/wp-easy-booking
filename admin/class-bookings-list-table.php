<?php
/**
 * Bookings list table.
 *
 * @package EasyBooking
 */

namespace EasyBooking\Admin;

use EasyBooking\Booking_Repository;
use EasyBooking\Helpers;
use EasyBooking\Settings;
use EasyBooking\Status;
use WP_List_Table;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Class Bookings_List_Table
 */
class Bookings_List_Table extends WP_List_Table {

	/**
	 * Screen option name for items per page.
	 *
	 * @var string
	 */
	const PER_PAGE_OPTION = 'easy_booking_per_page';

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

		parent::__construct(
			array(
				'singular' => 'booking',
				'plural'   => 'bookings',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Current filter values read from the request (all sanitized).
	 *
	 * Read-only GET parameters used for filtering; no state is changed,
	 * so no nonce is required here.
	 *
	 * @return array<string, string>
	 */
	public static function current_filters() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only list filters.
		$status    = isset( $_REQUEST['status'] ) ? sanitize_key( wp_unslash( $_REQUEST['status'] ) ) : '';
		$date_from = isset( $_REQUEST['date_from'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['date_from'] ) ) : '';
		$date_to   = isset( $_REQUEST['date_to'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['date_to'] ) ) : '';
		$search    = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';
		// phpcs:enable

		return array(
			'status'    => Status::is_valid( $status ) ? $status : '',
			'date_from' => Helpers::is_valid_date( $date_from ) ? $date_from : '',
			'date_to'   => Helpers::is_valid_date( $date_to ) ? $date_to : '',
			'search'    => $search,
		);
	}

	/**
	 * Columns.
	 *
	 * @return array<string, string>
	 */
	public function get_columns() {
		return array(
			'cb'            => '<input type="checkbox" />',
			'customer_name' => __( 'Customer', 'easy-booking' ),
			'service_name'  => __( 'Service', 'easy-booking' ),
			'booking_date'  => __( 'Appointment', 'easy-booking' ),
			'contact'       => __( 'Contact', 'easy-booking' ),
			'status'        => __( 'Status', 'easy-booking' ),
			'created_at'    => __( 'Booked on', 'easy-booking' ),
		);
	}

	/**
	 * Sortable columns.
	 *
	 * @return array<string, array>
	 */
	protected function get_sortable_columns() {
		return array(
			'customer_name' => array( 'customer_name', false ),
			'service_name'  => array( 'service_name', false ),
			'booking_date'  => array( 'booking_date', true ),
			'status'        => array( 'status', false ),
			'created_at'    => array( 'created_at', false ),
		);
	}

	/**
	 * Bulk actions.
	 *
	 * @return array<string, string>
	 */
	protected function get_bulk_actions() {
		return array(
			'confirm' => __( 'Confirm', 'easy-booking' ),
			'cancel'  => __( 'Cancel', 'easy-booking' ),
			'delete'  => __( 'Delete permanently', 'easy-booking' ),
		);
	}

	/**
	 * Status "views" links above the table (All | Pending | ...).
	 *
	 * @return array<string, string>
	 */
	protected function get_views() {
		$counts  = $this->repository->count_by_status();
		$current = self::current_filters()['status'];
		$base    = admin_url( 'admin.php?page=easy-booking' );
		$views   = array();

		$views['all'] = sprintf(
			'<a href="%1$s"%2$s>%3$s <span class="count">(%4$s)</span></a>',
			esc_url( $base ),
			'' === $current ? ' class="current" aria-current="page"' : '',
			esc_html__( 'All', 'easy-booking' ),
			esc_html( number_format_i18n( array_sum( $counts ) ) )
		);

		foreach ( Status::all() as $key => $label ) {
			$views[ $key ] = sprintf(
				'<a href="%1$s"%2$s>%3$s <span class="count">(%4$s)</span></a>',
				esc_url( add_query_arg( 'status', $key, $base ) ),
				$current === $key ? ' class="current" aria-current="page"' : '',
				esc_html( $label ),
				esc_html( number_format_i18n( isset( $counts[ $key ] ) ? $counts[ $key ] : 0 ) )
			);
		}

		return $views;
	}

	/**
	 * Date range filter + export button above the table.
	 *
	 * @param string $which Top or bottom.
	 * @return void
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}

		$filters = self::current_filters();
		?>
		<div class="alignleft actions eb-filters">
			<label for="eb-date-from" class="screen-reader-text"><?php esc_html_e( 'From date', 'easy-booking' ); ?></label>
			<input type="date" id="eb-date-from" name="date_from" value="<?php echo esc_attr( $filters['date_from'] ); ?>" title="<?php esc_attr_e( 'From date', 'easy-booking' ); ?>">
			<span aria-hidden="true">–</span>
			<label for="eb-date-to" class="screen-reader-text"><?php esc_html_e( 'To date', 'easy-booking' ); ?></label>
			<input type="date" id="eb-date-to" name="date_to" value="<?php echo esc_attr( $filters['date_to'] ); ?>" title="<?php esc_attr_e( 'To date', 'easy-booking' ); ?>">
			<?php submit_button( __( 'Filter', 'easy-booking' ), '', 'filter_action', false ); ?>
			<?php if ( $filters['date_from'] || $filters['date_to'] || $filters['search'] || $filters['status'] ) : ?>
				<a class="button-link eb-reset" href="<?php echo esc_url( admin_url( 'admin.php?page=easy-booking' ) ); ?>"><?php esc_html_e( 'Reset', 'easy-booking' ); ?></a>
			<?php endif; ?>
		</div>
		<div class="alignleft actions">
			<a class="button" href="<?php echo esc_url( Csv_Exporter::url( $filters ) ); ?>">
				<span class="dashicons dashicons-download" aria-hidden="true"></span>
				<?php esc_html_e( 'Export CSV', 'easy-booking' ); ?>
			</a>
		</div>
		<?php
	}

	/**
	 * Load items for the current page.
	 *
	 * @return void
	 */
	public function prepare_items() {
		$per_page = $this->get_items_per_page( self::PER_PAGE_OPTION, 20 );
		$filters  = self::current_filters();

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only sorting params, whitelisted in the repository.
		$orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'booking_date';
		$order   = isset( $_GET['order'] ) ? sanitize_key( wp_unslash( $_GET['order'] ) ) : 'desc';
		// phpcs:enable

		$args = array_merge(
			$filters,
			array(
				'orderby'  => $orderby,
				'order'    => $order,
				'per_page' => $per_page,
				'page'     => $this->get_pagenum(),
			)
		);

		$this->items = $this->repository->query( $args );
		$total       = $this->repository->count( $filters );

		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'customer_name' );

		$this->set_pagination_args(
			array(
				'total_items' => $total,
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $total / $per_page ),
			)
		);
	}

	/**
	 * Message shown when there are no bookings.
	 *
	 * @return void
	 */
	public function no_items() {
		esc_html_e( 'No bookings found.', 'easy-booking' );
	}

	/**
	 * Checkbox column.
	 *
	 * @param object $item Booking row.
	 * @return string
	 */
	protected function column_cb( $item ) {
		return sprintf(
			'<label class="screen-reader-text" for="eb-cb-%1$d">%2$s</label><input type="checkbox" id="eb-cb-%1$d" name="booking[]" value="%1$d" />',
			absint( $item->id ),
			/* translators: %s: customer name. */
			esc_html( sprintf( __( 'Select booking for %s', 'easy-booking' ), $item->customer_name ) )
		);
	}

	/**
	 * Customer column with row actions.
	 *
	 * @param object $item Booking row.
	 * @return string
	 */
	protected function column_customer_name( $item ) {
		$edit_url = Helpers::booking_admin_url( $item->id );
		$actions  = array(
			'edit' => sprintf( '<a href="%s">%s</a>', esc_url( $edit_url ), esc_html__( 'View / Edit', 'easy-booking' ) ),
		);

		if ( Status::CONFIRMED !== $item->status ) {
			$actions['confirm'] = sprintf( '<a href="%s">%s</a>', esc_url( $this->row_action_url( 'confirm', $item->id ) ), esc_html__( 'Confirm', 'easy-booking' ) );
		}

		if ( Status::CANCELLED !== $item->status ) {
			$actions['cancel'] = sprintf( '<a href="%s">%s</a>', esc_url( $this->row_action_url( 'cancel', $item->id ) ), esc_html__( 'Cancel', 'easy-booking' ) );
		}

		$actions['delete'] = sprintf(
			'<a href="%s" class="submitdelete eb-confirm-delete">%s</a>',
			esc_url( $this->row_action_url( 'delete', $item->id ) ),
			esc_html__( 'Delete', 'easy-booking' )
		);

		return sprintf(
			'<strong><a class="row-title" href="%1$s">%2$s</a></strong> <span class="eb-id">#%3$d</span>%4$s',
			esc_url( $edit_url ),
			esc_html( $item->customer_name ),
			absint( $item->id ),
			$this->row_actions( $actions )
		);
	}

	/**
	 * Service column.
	 *
	 * @param object $item Booking row.
	 * @return string
	 */
	protected function column_service_name( $item ) {
		return sprintf(
			'%1$s<br><span class="eb-muted">%2$s · %3$s</span>',
			esc_html( $item->service_name ),
			/* translators: %d: duration in minutes. */
			esc_html( sprintf( __( '%d min', 'easy-booking' ), $item->duration ) ),
			esc_html( Settings::format_price( $item->service_price ) )
		);
	}

	/**
	 * Appointment date/time column.
	 *
	 * @param object $item Booking row.
	 * @return string
	 */
	protected function column_booking_date( $item ) {
		return sprintf(
			'<strong>%1$s</strong><br><span class="eb-muted">%2$s</span>',
			esc_html( Helpers::format_date( $item->booking_date ) ),
			esc_html( Helpers::format_time_range( $item ) )
		);
	}

	/**
	 * Contact column.
	 *
	 * @param object $item Booking row.
	 * @return string
	 */
	protected function column_contact( $item ) {
		return sprintf(
			'<a href="mailto:%1$s">%2$s</a><br><span class="eb-muted">%3$s</span>',
			esc_attr( $item->customer_email ),
			esc_html( $item->customer_email ),
			esc_html( $item->customer_phone )
		);
	}

	/**
	 * Status badge column.
	 *
	 * @param object $item Booking row.
	 * @return string
	 */
	protected function column_status( $item ) {
		return self::status_badge( $item->status );
	}

	/**
	 * Created date column.
	 *
	 * @param object $item Booking row.
	 * @return string
	 */
	protected function column_created_at( $item ) {
		$timestamp = strtotime( $item->created_at );

		return esc_html(
			sprintf(
				/* translators: %s: human-readable time difference, e.g. "2 hours". */
				__( '%s ago', 'easy-booking' ),
				human_time_diff( $timestamp, (int) current_time( 'timestamp' ) ) // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- Compared with a local (site timezone) datetime.
			)
		);
	}

	/**
	 * Fallback column renderer.
	 *
	 * @param object $item        Booking row.
	 * @param string $column_name Column key.
	 * @return string
	 */
	protected function column_default( $item, $column_name ) {
		return isset( $item->$column_name ) ? esc_html( $item->$column_name ) : '';
	}

	/**
	 * HTML for a status badge.
	 *
	 * @param string $status Status key.
	 * @return string
	 */
	public static function status_badge( $status ) {
		return sprintf(
			'<span class="eb-badge eb-badge--%1$s">%2$s</span>',
			esc_attr( sanitize_html_class( $status ) ),
			esc_html( Status::label( $status ) )
		);
	}

	/**
	 * Nonce-protected URL for a single-row action.
	 *
	 * @param string $action     confirm|cancel|delete.
	 * @param int    $booking_id Booking ID.
	 * @return string
	 */
	private function row_action_url( $action, $booking_id ) {
		return wp_nonce_url(
			add_query_arg(
				array(
					'page'       => 'easy-booking',
					'eb_action'  => $action,
					'booking_id' => absint( $booking_id ),
				),
				admin_url( 'admin.php' )
			),
			'easy_booking_row_' . absint( $booking_id )
		);
	}
}
