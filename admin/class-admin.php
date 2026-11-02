<?php
/**
 * Admin controller: menus, assets, list actions and booking edits.
 *
 * @package EasyBooking
 */

namespace EasyBooking\Admin;

use EasyBooking\Booking_Repository;
use EasyBooking\Frontend\Ajax;
use EasyBooking\Helpers;
use EasyBooking\Plugin;
use EasyBooking\Settings;
use EasyBooking\Status;

defined( 'ABSPATH' ) || exit;

/**
 * Class Admin
 */
class Admin {

	/**
	 * Main menu slug.
	 *
	 * @var string
	 */
	const MENU_SLUG = 'easy-booking';

	/**
	 * Action name (admin-post.php) for saving a single booking.
	 *
	 * @var string
	 */
	const SAVE_ACTION = 'easy_booking_save';

	/**
	 * Booking repository.
	 *
	 * @var Booking_Repository
	 */
	private $repository;

	/**
	 * Settings page controller.
	 *
	 * @var Settings_Page
	 */
	private $settings_page;

	/**
	 * List table instance (created on the bookings screen only).
	 *
	 * @var Bookings_List_Table|null
	 */
	private $list_table = null;

	/**
	 * Hook suffixes of our admin pages.
	 *
	 * @var string[]
	 */
	private $hooks = array();

	/**
	 * Constructor.
	 *
	 * @param Booking_Repository $repository Booking repository.
	 */
	public function __construct( Booking_Repository $repository ) {
		$this->repository    = $repository;
		$this->settings_page = new Settings_Page();
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_' . self::SAVE_ACTION, array( $this, 'handle_save' ) );
		add_filter( 'set-screen-option', array( $this, 'save_screen_option' ), 10, 3 );
		add_filter( 'plugin_action_links_' . plugin_basename( EASY_BOOKING_FILE ), array( $this, 'plugin_links' ) );

		$this->settings_page->register();
		( new Dashboard_Widget( $this->repository ) )->register();
		( new Csv_Exporter( $this->repository ) )->register();
	}

	/**
	 * Register the "Bookings" menu and sub pages.
	 *
	 * @return void
	 */
	public function add_menu() {
		$capability = Plugin::capability();
		$pending    = $this->repository->count( array( 'status' => Status::PENDING ) );
		$bubble     = $pending ? sprintf( ' <span class="awaiting-mod count-%1$d"><span class="pending-count">%2$s</span></span>', $pending, number_format_i18n( $pending ) ) : '';

		$this->hooks['list'] = add_menu_page(
			__( 'Bookings', 'easy-booking' ),
			__( 'Bookings', 'easy-booking' ) . $bubble,
			$capability,
			self::MENU_SLUG,
			array( $this, 'render_bookings_page' ),
			'dashicons-calendar-alt',
			26
		);

		add_submenu_page( self::MENU_SLUG, __( 'All Bookings', 'easy-booking' ), __( 'All Bookings', 'easy-booking' ), $capability, self::MENU_SLUG, array( $this, 'render_bookings_page' ) );

		$this->hooks['settings'] = add_submenu_page(
			self::MENU_SLUG,
			__( 'Booking Settings', 'easy-booking' ),
			__( 'Settings', 'easy-booking' ),
			$capability,
			Settings_Page::PAGE,
			array( $this->settings_page, 'render' )
		);

		add_action( 'load-' . $this->hooks['list'], array( $this, 'on_load_bookings_page' ) );
	}

	/**
	 * Add a "Settings" link on the Plugins screen.
	 *
	 * @param string[] $links Existing links.
	 * @return string[]
	 */
	public function plugin_links( $links ) {
		array_unshift(
			$links,
			sprintf( '<a href="%s">%s</a>', esc_url( admin_url( 'admin.php?page=' . Settings_Page::PAGE ) ), esc_html__( 'Settings', 'easy-booking' ) )
		);

		return $links;
	}

	/**
	 * Enqueue admin CSS/JS on our screens (and the dashboard for the widget).
	 *
	 * @param string $hook_suffix Current admin page.
	 * @return void
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( ! in_array( $hook_suffix, array_merge( array_values( $this->hooks ), array( 'index.php' ) ), true ) ) {
			return;
		}

		wp_enqueue_style( 'easy-booking-admin', EASY_BOOKING_URL . 'assets/css/admin.css', array(), EASY_BOOKING_VERSION );

		if ( 'index.php' === $hook_suffix ) {
			return;
		}

		wp_enqueue_script( 'easy-booking-admin', EASY_BOOKING_URL . 'assets/js/admin.js', array(), EASY_BOOKING_VERSION, true );
		wp_localize_script(
			'easy-booking-admin',
			'easyBookingAdmin',
			array(
				'confirmDelete' => __( 'Delete the selected booking(s) permanently? This cannot be undone.', 'easy-booking' ),
				'confirmRemove' => __( 'Remove this service?', 'easy-booking' ),
			)
		);
	}

	/**
	 * Persist the "items per page" screen option.
	 *
	 * @param mixed  $status Default false.
	 * @param string $option Option name.
	 * @param mixed  $value  Submitted value.
	 * @return mixed
	 */
	public function save_screen_option( $status, $option, $value ) {
		return Bookings_List_Table::PER_PAGE_OPTION === $option ? min( 200, max( 1, absint( $value ) ) ) : $status;
	}

	/**
	 * Runs before the bookings screen outputs anything: handle actions
	 * (so we can redirect) and set up screen options.
	 *
	 * @return void
	 */
	public function on_load_bookings_page() {
		if ( ! current_user_can( Plugin::capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to manage bookings.', 'easy-booking' ), 403 );
		}

		$this->process_row_action();
		$this->process_bulk_action();

		// Strip nonce/referer noise from the URL after a filter/search submit, like core does.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only used to clean the URL.
		if ( ! empty( $_GET['_wp_http_referer'] ) && isset( $_SERVER['REQUEST_URI'] ) ) {
			wp_safe_redirect( remove_query_arg( array( '_wp_http_referer', '_wpnonce', 'action', 'action2', 'filter_action' ), wp_unslash( $_SERVER['REQUEST_URI'] ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- URL passed through wp_safe_redirect().
			exit;
		}

		add_screen_option(
			'per_page',
			array(
				'label'   => __( 'Bookings per page', 'easy-booking' ),
				'default' => 20,
				'option'  => Bookings_List_Table::PER_PAGE_OPTION,
			)
		);

		$this->list_table = new Bookings_List_Table( $this->repository );
	}

	/**
	 * Handle a single-row action link (confirm / cancel / delete).
	 *
	 * @return void
	 */
	private function process_row_action() {
		if ( empty( $_GET['eb_action'] ) || empty( $_GET['booking_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified below.
			return;
		}

		$action = sanitize_key( wp_unslash( $_GET['eb_action'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified below.
		$id     = absint( $_GET['booking_id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified below.

		check_admin_referer( 'easy_booking_row_' . $id );

		$this->redirect_with_result( $this->apply_action( $action, array( $id ) ), $action );
	}

	/**
	 * Handle bulk actions submitted from the list table.
	 *
	 * @return void
	 */
	private function process_bulk_action() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Nonce verified below before any change.
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '-1';
		if ( '-1' === $action && isset( $_GET['action2'] ) ) {
			$action = sanitize_key( wp_unslash( $_GET['action2'] ) );
		}

		if ( ! in_array( $action, array( 'confirm', 'cancel', 'delete' ), true ) || empty( $_GET['booking'] ) ) {
			return;
		}

		$ids = array_filter( array_map( 'absint', (array) wp_unslash( $_GET['booking'] ) ) );
		// phpcs:enable

		check_admin_referer( 'bulk-bookings' );

		$this->redirect_with_result( $this->apply_action( $action, $ids ), $action );
	}

	/**
	 * Apply an action to a set of booking IDs.
	 *
	 * @param string $action confirm|cancel|delete.
	 * @param int[]  $ids    Booking IDs.
	 * @return array{done:int,failed:int}
	 */
	private function apply_action( $action, array $ids ) {
		$done   = 0;
		$failed = 0;
		$map    = array(
			'confirm' => Status::CONFIRMED,
			'cancel'  => Status::CANCELLED,
		);

		foreach ( $ids as $id ) {
			if ( 'delete' === $action ) {
				$ok = $this->repository->delete( $id );
			} elseif ( isset( $map[ $action ] ) ) {
				$ok = true === $this->repository->set_status( $id, $map[ $action ] );
			} else {
				$ok = false;
			}

			$ok ? ++$done : ++$failed;
		}

		return array(
			'done'   => $done,
			'failed' => $failed,
		);
	}

	/**
	 * Redirect back to the list with a result notice.
	 *
	 * @param array  $result Result counts.
	 * @param string $action Action performed.
	 * @return void
	 */
	private function redirect_with_result( array $result, $action ) {
		$url = add_query_arg(
			array(
				'page'      => self::MENU_SLUG,
				'eb_notice' => 'delete' === $action ? 'deleted' : 'updated',
				'eb_count'  => $result['done'],
				'eb_failed' => $result['failed'],
			),
			admin_url( 'admin.php' )
		);

		// Preserve the current filters.
		foreach ( Bookings_List_Table::current_filters() as $key => $value ) {
			if ( '' !== $value ) {
				$url = add_query_arg( 'search' === $key ? 's' : $key, rawurlencode( $value ), $url );
			}
		}

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Router for the bookings page: list or single edit view.
	 *
	 * @return void
	 */
	public function render_bookings_page() {
		if ( ! current_user_can( Plugin::capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to manage bookings.', 'easy-booking' ), 403 );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only routing.
		$action     = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
		$booking_id = isset( $_GET['booking_id'] ) ? absint( $_GET['booking_id'] ) : 0;
		// phpcs:enable

		if ( 'edit' === $action && $booking_id ) {
			$this->render_edit_page( $booking_id );
			return;
		}

		$this->render_list_page();
	}

	/**
	 * Bookings list screen.
	 *
	 * @return void
	 */
	private function render_list_page() {
		$table = $this->list_table ? $this->list_table : new Bookings_List_Table( $this->repository );
		$table->prepare_items();
		?>
		<div class="wrap eb-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Bookings', 'easy-booking' ); ?></h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . Settings_Page::PAGE ) ); ?>" class="page-title-action"><?php esc_html_e( 'Settings', 'easy-booking' ); ?></a>
			<hr class="wp-header-end">

			<?php $this->render_notice(); ?>

			<?php $table->views(); ?>

			<form method="get" class="eb-list-form">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::MENU_SLUG ); ?>">
				<?php
				$filters = Bookings_List_Table::current_filters();
				if ( $filters['status'] ) {
					printf( '<input type="hidden" name="status" value="%s">', esc_attr( $filters['status'] ) );
				}
				$table->search_box( __( 'Search bookings', 'easy-booking' ), 'eb-search' );
				$table->display();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Single booking view / edit screen.
	 *
	 * @param int $booking_id Booking ID.
	 * @return void
	 */
	private function render_edit_page( $booking_id ) {
		$booking = $this->repository->find( $booking_id );

		if ( ! $booking ) {
			echo '<div class="wrap"><h1>' . esc_html__( 'Booking not found', 'easy-booking' ) . '</h1><p><a href="' . esc_url( admin_url( 'admin.php?page=' . self::MENU_SLUG ) ) . '">' . esc_html__( '&larr; Back to bookings', 'easy-booking' ) . '</a></p></div>';
			return;
		}

		$slot_step = (int) Settings::get( 'slot_length', 30 ) * 60;

		include EASY_BOOKING_PATH . 'admin/views/booking-edit.php';
	}

	/**
	 * Save handler for the single booking form (admin-post.php).
	 *
	 * @return void
	 */
	public function handle_save() {
		$booking_id = isset( $_POST['booking_id'] ) ? absint( $_POST['booking_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified on the next line.

		if ( ! current_user_can( Plugin::capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to manage bookings.', 'easy-booking' ), 403 );
		}

		check_admin_referer( self::SAVE_ACTION . '_' . $booking_id );

		$booking = $this->repository->find( $booking_id );
		if ( ! $booking ) {
			wp_die( esc_html__( 'Booking not found.', 'easy-booking' ), 404 );
		}

		$name   = isset( $_POST['customer_name'] ) ? sanitize_text_field( wp_unslash( $_POST['customer_name'] ) ) : '';
		$email  = isset( $_POST['customer_email'] ) ? sanitize_email( wp_unslash( $_POST['customer_email'] ) ) : '';
		$phone  = isset( $_POST['customer_phone'] ) ? Ajax::sanitize_phone( wp_unslash( $_POST['customer_phone'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized by sanitize_phone().
		$date   = isset( $_POST['booking_date'] ) ? sanitize_text_field( wp_unslash( $_POST['booking_date'] ) ) : '';
		$start  = isset( $_POST['start_time'] ) ? Settings::sanitize_time( sanitize_text_field( wp_unslash( $_POST['start_time'] ) ) ) : '';
		$status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
		$notes  = isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '';
		$notify = ! empty( $_POST['notify_customer'] );

		$error = '';
		if ( '' === $name ) {
			$error = 'name';
		} elseif ( ! is_email( $email ) ) {
			$error = 'email';
		} elseif ( ! Helpers::is_valid_date( $date ) || '' === $start ) {
			$error = 'datetime';
		} elseif ( ! Status::is_valid( $status ) ) {
			$error = 'status';
		}

		if ( '' === $error ) {
			$start_minutes = Helpers::time_to_minutes( $start );
			$end_minutes   = $start_minutes + (int) $booking->duration;

			if ( $end_minutes > 24 * 60 ) {
				$error = 'datetime';
			}
		}

		if ( '' !== $error ) {
			$this->redirect_to_edit( $booking_id, 'error_' . $error );
		}

		// Respect the "notify customer" checkbox for this request only.
		if ( ! $notify ) {
			add_filter( 'easy_booking_send_status_email', '__return_false' );
		}

		$result = $this->repository->update(
			$booking_id,
			array(
				'customer_name'  => $name,
				'customer_email' => $email,
				'customer_phone' => $phone,
				'booking_date'   => $date,
				'start_time'     => Helpers::minutes_to_time( $start_minutes ),
				'end_time'       => Helpers::minutes_to_time( $end_minutes ),
				'status'         => $status,
				'notes'          => $notes,
			)
		);

		if ( is_wp_error( $result ) ) {
			$this->redirect_to_edit( $booking_id, 'easy_booking_slot_taken' === $result->get_error_code() ? 'error_conflict' : 'error_db' );
		}

		$this->redirect_to_edit( $booking_id, 'saved' );
	}

	/**
	 * Redirect to the edit screen with a notice key.
	 *
	 * @param int    $booking_id Booking ID.
	 * @param string $notice     Notice key.
	 * @return void
	 */
	private function redirect_to_edit( $booking_id, $notice ) {
		wp_safe_redirect( add_query_arg( 'eb_notice', $notice, Helpers::booking_admin_url( $booking_id ) ) );
		exit;
	}

	/**
	 * Output an admin notice based on the `eb_notice` query arg.
	 *
	 * Only predefined messages are shown; nothing from the URL is echoed.
	 *
	 * @return void
	 */
	public function render_notice() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Display-only.
		$notice = isset( $_GET['eb_notice'] ) ? sanitize_key( wp_unslash( $_GET['eb_notice'] ) ) : '';
		$count  = isset( $_GET['eb_count'] ) ? absint( $_GET['eb_count'] ) : 0;
		$failed = isset( $_GET['eb_failed'] ) ? absint( $_GET['eb_failed'] ) : 0;
		// phpcs:enable

		if ( '' === $notice ) {
			return;
		}

		$type     = 'success';
		$messages = array(
			/* translators: %s: number of bookings. */
			'updated'        => sprintf( _n( '%s booking updated.', '%s bookings updated.', $count, 'easy-booking' ), number_format_i18n( $count ) ),
			/* translators: %s: number of bookings. */
			'deleted'        => sprintf( _n( '%s booking deleted.', '%s bookings deleted.', $count, 'easy-booking' ), number_format_i18n( $count ) ),
			'saved'          => __( 'Booking saved.', 'easy-booking' ),
			'error_name'     => __( 'Please enter the customer name.', 'easy-booking' ),
			'error_email'    => __( 'Please enter a valid email address.', 'easy-booking' ),
			'error_datetime' => __( 'Please enter a valid date and start time.', 'easy-booking' ),
			'error_status'   => __( 'Please choose a valid status.', 'easy-booking' ),
			'error_conflict' => __( 'The booking was not saved: this time overlaps with another active booking.', 'easy-booking' ),
			'error_db'       => __( 'The booking could not be saved. Please try again.', 'easy-booking' ),
		);

		if ( ! isset( $messages[ $notice ] ) ) {
			return;
		}

		$message = $messages[ $notice ];

		if ( 0 === strpos( $notice, 'error_' ) ) {
			$type = 'error';
		} elseif ( $failed ) {
			$type     = 'warning';
			$message .= ' ' . sprintf(
				/* translators: %s: number of bookings. */
				_n( '%s booking could not be changed (it may overlap another booking).', '%s bookings could not be changed (they may overlap other bookings).', $failed, 'easy-booking' ),
				number_format_i18n( $failed )
			);
		}

		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $type ),
			esc_html( $message )
		);
	}
}
