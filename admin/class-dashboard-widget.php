<?php
/**
 * Dashboard widget: today's and upcoming bookings.
 *
 * @package EasyBooking
 */

namespace EasyBooking\Admin;

use EasyBooking\Booking_Repository;
use EasyBooking\Helpers;
use EasyBooking\Plugin;
use EasyBooking\Status;

defined( 'ABSPATH' ) || exit;

/**
 * Class Dashboard_Widget
 */
class Dashboard_Widget {

	/**
	 * Number of upcoming days shown.
	 *
	 * @var int
	 */
	const UPCOMING_DAYS = 7;

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
		add_action( 'wp_dashboard_setup', array( $this, 'add_widget' ) );
	}

	/**
	 * Add the widget for users who can manage bookings.
	 *
	 * @return void
	 */
	public function add_widget() {
		if ( ! current_user_can( Plugin::capability() ) ) {
			return;
		}

		wp_add_dashboard_widget( 'easy_booking_dashboard', __( 'Bookings', 'easy-booking' ), array( $this, 'render' ) );
	}

	/**
	 * Render the widget.
	 *
	 * @return void
	 */
	public function render() {
		$today    = Helpers::now()->format( 'Y-m-d' );
		$tomorrow = Helpers::now()->modify( '+1 day' )->format( 'Y-m-d' );
		$until    = Helpers::now()->modify( '+' . self::UPCOMING_DAYS . ' days' )->format( 'Y-m-d' );

		$todays = $this->repository->query(
			array(
				'date_from'      => $today,
				'date_to'        => $today,
				'exclude_status' => Status::CANCELLED,
				'orderby'        => 'booking_date',
				'order'          => 'ASC',
				'per_page'       => 0,
			)
		);

		$upcoming = $this->repository->query(
			array(
				'date_from'      => $tomorrow,
				'date_to'        => $until,
				'exclude_status' => Status::CANCELLED,
				'orderby'        => 'booking_date',
				'order'          => 'ASC',
				'per_page'       => 10,
			)
		);

		$upcoming_total = $this->repository->count(
			array(
				'date_from'      => $tomorrow,
				'date_to'        => $until,
				'exclude_status' => Status::CANCELLED,
			)
		);

		$pending = $this->repository->count( array( 'status' => Status::PENDING ) );
		?>
		<div class="eb-widget">
			<div class="eb-widget__stats">
				<div class="eb-widget__stat">
					<span class="eb-widget__number"><?php echo esc_html( number_format_i18n( count( $todays ) ) ); ?></span>
					<span class="eb-widget__label"><?php esc_html_e( 'Today', 'easy-booking' ); ?></span>
				</div>
				<div class="eb-widget__stat">
					<span class="eb-widget__number"><?php echo esc_html( number_format_i18n( $upcoming_total ) ); ?></span>
					<span class="eb-widget__label">
						<?php
						/* translators: %d: number of days. */
						echo esc_html( sprintf( __( 'Next %d days', 'easy-booking' ), self::UPCOMING_DAYS ) );
						?>
					</span>
				</div>
				<a class="eb-widget__stat eb-widget__stat--link" href="<?php echo esc_url( admin_url( 'admin.php?page=easy-booking&status=pending' ) ); ?>">
					<span class="eb-widget__number"><?php echo esc_html( number_format_i18n( $pending ) ); ?></span>
					<span class="eb-widget__label"><?php esc_html_e( 'Pending', 'easy-booking' ); ?></span>
				</a>
			</div>

			<h3 class="eb-widget__heading"><?php esc_html_e( "Today's bookings", 'easy-booking' ); ?></h3>
			<?php $this->render_list( $todays, __( 'No bookings today.', 'easy-booking' ), false ); ?>

			<h3 class="eb-widget__heading"><?php esc_html_e( 'Upcoming', 'easy-booking' ); ?></h3>
			<?php $this->render_list( $upcoming, __( 'No upcoming bookings.', 'easy-booking' ), true ); ?>

			<p class="eb-widget__footer">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=easy-booking' ) ); ?>"><?php esc_html_e( 'View all bookings', 'easy-booking' ); ?> &rarr;</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Render a compact list of bookings.
	 *
	 * @param object[] $bookings   Bookings.
	 * @param string   $empty_text Empty-state message.
	 * @param bool     $show_date  Whether to show the date.
	 * @return void
	 */
	private function render_list( array $bookings, $empty_text, $show_date ) {
		if ( ! $bookings ) {
			echo '<p class="eb-widget__empty">' . esc_html( $empty_text ) . '</p>';
			return;
		}

		echo '<ul class="eb-widget__list">';
		foreach ( $bookings as $booking ) {
			$when = Helpers::format_time( $booking->start_time );
			if ( $show_date ) {
				$when = Helpers::format_date( $booking->booking_date ) . ', ' . $when;
			}

			printf(
				'<li><span class="eb-widget__time">%1$s</span> <a href="%2$s">%3$s</a> <span class="eb-muted">%4$s</span> %5$s</li>',
				esc_html( $when ),
				esc_url( Helpers::booking_admin_url( $booking->id ) ),
				esc_html( $booking->customer_name ),
				esc_html( $booking->service_name ),
				Bookings_List_Table::status_badge( $booking->status ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside status_badge().
			);
		}
		echo '</ul>';
	}
}
