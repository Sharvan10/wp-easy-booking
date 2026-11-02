<?php
/**
 * Single booking view / edit screen.
 *
 * Variables provided by Admin::render_edit_page():
 *
 * @var object $booking   Booking row.
 * @var int    $slot_step Slot length in seconds (time input step).
 * @var \EasyBooking\Admin\Admin $this Admin controller.
 *
 * @package EasyBooking
 */

use EasyBooking\Admin\Admin;
use EasyBooking\Admin\Bookings_List_Table;
use EasyBooking\Helpers;
use EasyBooking\Settings;
use EasyBooking\Status;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template is included inside a class method, so variables are not global.

$delete_url = wp_nonce_url(
	add_query_arg(
		array(
			'page'       => Admin::MENU_SLUG,
			'eb_action'  => 'delete',
			'booking_id' => $booking->id,
		),
		admin_url( 'admin.php' )
	),
	'easy_booking_row_' . $booking->id
);
?>
<div class="wrap eb-wrap eb-edit">
	<h1 class="wp-heading-inline">
		<?php
		/* translators: %d: booking ID. */
		echo esc_html( sprintf( __( 'Booking #%d', 'easy-booking' ), $booking->id ) );
		?>
	</h1>
	<?php echo Bookings_List_Table::status_badge( $booking->status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside status_badge(). ?>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . Admin::MENU_SLUG ) ); ?>" class="page-title-action"><?php esc_html_e( '← All bookings', 'easy-booking' ); ?></a>
	<hr class="wp-header-end">

	<?php $this->render_notice(); ?>

	<div class="eb-edit__layout">
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="eb-card eb-edit__form">
			<input type="hidden" name="action" value="<?php echo esc_attr( Admin::SAVE_ACTION ); ?>">
			<input type="hidden" name="booking_id" value="<?php echo esc_attr( $booking->id ); ?>">
			<?php wp_nonce_field( Admin::SAVE_ACTION . '_' . $booking->id ); ?>

			<h2><?php esc_html_e( 'Customer', 'easy-booking' ); ?></h2>
			<div class="eb-form-grid">
				<p>
					<label for="eb-customer-name"><?php esc_html_e( 'Name', 'easy-booking' ); ?></label>
					<input type="text" id="eb-customer-name" name="customer_name" class="widefat" value="<?php echo esc_attr( $booking->customer_name ); ?>" required>
				</p>
				<p>
					<label for="eb-customer-email"><?php esc_html_e( 'Email', 'easy-booking' ); ?></label>
					<input type="email" id="eb-customer-email" name="customer_email" class="widefat" value="<?php echo esc_attr( $booking->customer_email ); ?>" required>
				</p>
				<p>
					<label for="eb-customer-phone"><?php esc_html_e( 'Phone', 'easy-booking' ); ?></label>
					<input type="tel" id="eb-customer-phone" name="customer_phone" class="widefat" value="<?php echo esc_attr( $booking->customer_phone ); ?>">
				</p>
			</div>

			<h2><?php esc_html_e( 'Appointment', 'easy-booking' ); ?></h2>
			<div class="eb-form-grid">
				<p>
					<label for="eb-booking-date"><?php esc_html_e( 'Date', 'easy-booking' ); ?></label>
					<input type="date" id="eb-booking-date" name="booking_date" class="widefat" value="<?php echo esc_attr( $booking->booking_date ); ?>" required>
				</p>
				<p>
					<label for="eb-start-time"><?php esc_html_e( 'Start time', 'easy-booking' ); ?></label>
					<input type="time" id="eb-start-time" name="start_time" class="widefat" value="<?php echo esc_attr( substr( $booking->start_time, 0, 5 ) ); ?>" step="<?php echo esc_attr( $slot_step ); ?>" required>
				</p>
				<p>
					<label for="eb-status"><?php esc_html_e( 'Status', 'easy-booking' ); ?></label>
					<select id="eb-status" name="status" class="widefat">
						<?php foreach ( Status::all() as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $booking->status, $key ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
			</div>

			<p>
				<label for="eb-notes"><?php esc_html_e( 'Notes', 'easy-booking' ); ?></label>
				<textarea id="eb-notes" name="notes" class="widefat" rows="4"><?php echo esc_textarea( $booking->notes ); ?></textarea>
			</p>

			<p class="eb-checkbox">
				<label>
					<input type="checkbox" name="notify_customer" value="1" checked>
					<?php esc_html_e( 'Email the customer if the status changes', 'easy-booking' ); ?>
				</label>
			</p>

			<div class="eb-edit__actions">
				<?php submit_button( __( 'Save booking', 'easy-booking' ), 'primary', 'submit', false ); ?>
				<a class="eb-link-delete eb-confirm-delete" href="<?php echo esc_url( $delete_url ); ?>"><?php esc_html_e( 'Delete booking', 'easy-booking' ); ?></a>
			</div>
		</form>

		<aside class="eb-card eb-edit__summary">
			<h2><?php esc_html_e( 'Summary', 'easy-booking' ); ?></h2>
			<dl class="eb-summary">
				<dt><?php esc_html_e( 'Service', 'easy-booking' ); ?></dt>
				<dd><?php echo esc_html( $booking->service_name ); ?></dd>

				<dt><?php esc_html_e( 'Price', 'easy-booking' ); ?></dt>
				<dd><?php echo esc_html( Settings::format_price( $booking->service_price ) ); ?></dd>

				<dt><?php esc_html_e( 'Duration', 'easy-booking' ); ?></dt>
				<dd>
					<?php
					/* translators: %d: duration in minutes. */
					echo esc_html( sprintf( __( '%d minutes', 'easy-booking' ), $booking->duration ) );
					?>
				</dd>

				<dt><?php esc_html_e( 'When', 'easy-booking' ); ?></dt>
				<dd><?php echo esc_html( Helpers::format_date( $booking->booking_date ) ); ?><br><?php echo esc_html( Helpers::format_time_range( $booking ) ); ?></dd>

				<dt><?php esc_html_e( 'Booked on', 'easy-booking' ); ?></dt>
				<dd><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $booking->created_at ) ); ?></dd>

				<dt><?php esc_html_e( 'Last updated', 'easy-booking' ); ?></dt>
				<dd><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $booking->updated_at ) ); ?></dd>
			</dl>
			<p>
				<a class="button" href="mailto:<?php echo esc_attr( $booking->customer_email ); ?>"><span class="dashicons dashicons-email" aria-hidden="true"></span> <?php esc_html_e( 'Email customer', 'easy-booking' ); ?></a>
			</p>
		</aside>
	</div>
</div>
