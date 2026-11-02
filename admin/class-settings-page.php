<?php
/**
 * Settings screen built with the WordPress Settings API.
 *
 * @package EasyBooking
 */

namespace EasyBooking\Admin;

use EasyBooking\Emails;
use EasyBooking\Plugin;
use EasyBooking\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Class Settings_Page
 */
class Settings_Page {

	/**
	 * Settings page slug.
	 *
	 * @var string
	 */
	const PAGE = 'easy-booking-settings';

	/**
	 * Settings group (option_group) name.
	 *
	 * @var string
	 */
	const GROUP = 'easy_booking_settings_group';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );

		// Allow the custom capability to save the settings form.
		add_filter( 'option_page_capability_' . self::GROUP, array( Plugin::class, 'capability' ) );

		// Keep the in-memory settings cache in sync.
		add_action( 'update_option_' . Settings::OPTION_NAME, array( Settings::class, 'flush_cache' ) );
	}

	/**
	 * Register the setting, sections and fields.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			self::GROUP,
			Settings::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( Settings::class, 'sanitize' ),
				'default'           => Settings::defaults(),
				'show_in_rest'      => false,
			)
		);

		// Services.
		add_settings_section( 'eb_services', __( 'Services', 'easy-booking' ), array( $this, 'section_services' ), self::PAGE );
		add_settings_field( 'services', __( 'Services offered', 'easy-booking' ), array( $this, 'field_services' ), self::PAGE, 'eb_services' );

		// Schedule.
		add_settings_section( 'eb_schedule', __( 'Schedule', 'easy-booking' ), array( $this, 'section_schedule' ), self::PAGE );
		add_settings_field( 'working_days', __( 'Working days', 'easy-booking' ), array( $this, 'field_working_days' ), self::PAGE, 'eb_schedule' );
		add_settings_field( 'working_hours', __( 'Working hours', 'easy-booking' ), array( $this, 'field_working_hours' ), self::PAGE, 'eb_schedule' );
		add_settings_field( 'slot_length', __( 'Slot length', 'easy-booking' ), array( $this, 'field_slot_length' ), self::PAGE, 'eb_schedule', array( 'label_for' => 'eb-slot-length' ) );
		add_settings_field( 'booking_window', __( 'Booking window', 'easy-booking' ), array( $this, 'field_booking_window' ), self::PAGE, 'eb_schedule', array( 'label_for' => 'eb-booking-window' ) );

		// General.
		add_settings_section( 'eb_general', __( 'General', 'easy-booking' ), '__return_false', self::PAGE );
		add_settings_field( 'admin_email', __( 'Notification email', 'easy-booking' ), array( $this, 'field_admin_email' ), self::PAGE, 'eb_general', array( 'label_for' => 'eb-admin-email' ) );
		add_settings_field( 'currency', __( 'Currency symbol', 'easy-booking' ), array( $this, 'field_currency' ), self::PAGE, 'eb_general', array( 'label_for' => 'eb-currency' ) );

		// Email templates.
		add_settings_section( 'eb_emails', __( 'Email templates', 'easy-booking' ), array( $this, 'section_emails' ), self::PAGE );

		$templates = array(
			'customer' => __( 'Customer confirmation', 'easy-booking' ),
			'admin'    => __( 'Admin notification', 'easy-booking' ),
			'status'   => __( 'Status change (customer)', 'easy-booking' ),
		);

		foreach ( $templates as $key => $label ) {
			add_settings_field( 'email_' . $key, $label, array( $this, 'field_email_template' ), self::PAGE, 'eb_emails', array( 'template' => $key ) );
		}
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( Plugin::capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'easy-booking' ), 403 );
		}
		?>
		<div class="wrap eb-wrap eb-settings">
			<h1><?php esc_html_e( 'Booking Settings', 'easy-booking' ); ?></h1>

			<div class="eb-card eb-shortcode-hint">
				<?php
				printf(
					/* translators: %s: shortcode. */
					esc_html__( 'Add the booking form to any page or post with the %s shortcode.', 'easy-booking' ),
					'<code>[easy_booking]</code>'
				);
				?>
			</div>

			<?php settings_errors(); ?>

			<form action="options.php" method="post">
				<?php
				settings_fields( self::GROUP );
				do_settings_sections( self::PAGE );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Services section intro.
	 *
	 * @return void
	 */
	public function section_services() {
		echo '<p>' . esc_html__( 'Define the services customers can book. Clear a service name to remove it.', 'easy-booking' ) . '</p>';
	}

	/**
	 * Schedule section intro.
	 *
	 * @return void
	 */
	public function section_schedule() {
		echo '<p>' . esc_html__( 'Time slots are generated within your working hours. Already booked slots are hidden automatically.', 'easy-booking' ) . '</p>';
	}

	/**
	 * Email section intro with placeholder reference.
	 *
	 * @return void
	 */
	public function section_emails() {
		echo '<p>' . esc_html__( 'Emails are sent as plain text. Available placeholders:', 'easy-booking' ) . '</p>';
		echo '<p class="eb-placeholders">';
		foreach ( Emails::placeholder_names() as $placeholder ) {
			echo '<code>' . esc_html( $placeholder ) . '</code> ';
		}
		echo '</p>';
	}

	/**
	 * Services repeater field.
	 *
	 * @return void
	 */
	public function field_services() {
		$services = Settings::services();
		$name     = Settings::OPTION_NAME . '[services]';
		?>
		<table class="widefat striped eb-services" data-name="<?php echo esc_attr( $name ); ?>">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Name', 'easy-booking' ); ?></th>
					<th scope="col" class="eb-col-small"><?php esc_html_e( 'Duration (min)', 'easy-booking' ); ?></th>
					<th scope="col" class="eb-col-small">
						<?php
						/* translators: %s: currency symbol. */
						echo esc_html( sprintf( __( 'Price (%s)', 'easy-booking' ), Settings::get( 'currency', '$' ) ) );
						?>
					</th>
					<th scope="col" class="eb-col-action"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'easy-booking' ); ?></span></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $services as $index => $service ) {
					$this->service_row( $name, $index, $service );
				}
				?>
			</tbody>
		</table>

		<script type="text/html" id="tmpl-eb-service-row">
			<?php $this->service_row( $name, '__INDEX__', array() ); ?>
		</script>

		<p><button type="button" class="button eb-add-service"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span> <?php esc_html_e( 'Add service', 'easy-booking' ); ?></button></p>
		<?php
	}

	/**
	 * Output a single service table row.
	 *
	 * @param string     $name    Base input name.
	 * @param int|string $index   Row index.
	 * @param array      $service Service values.
	 * @return void
	 */
	private function service_row( $name, $index, array $service ) {
		$service = wp_parse_args(
			$service,
			array(
				'id'       => '',
				'name'     => '',
				'duration' => 30,
				'price'    => 0,
			)
		);
		$base    = $name . '[' . $index . ']';
		?>
		<tr class="eb-service-row">
			<td>
				<input type="hidden" name="<?php echo esc_attr( $base . '[id]' ); ?>" value="<?php echo esc_attr( $service['id'] ); ?>">
				<label class="screen-reader-text" for="<?php echo esc_attr( 'eb-service-name-' . $index ); ?>"><?php esc_html_e( 'Service name', 'easy-booking' ); ?></label>
				<input type="text" class="regular-text" id="<?php echo esc_attr( 'eb-service-name-' . $index ); ?>" name="<?php echo esc_attr( $base . '[name]' ); ?>" value="<?php echo esc_attr( $service['name'] ); ?>" placeholder="<?php esc_attr_e( 'e.g. Haircut', 'easy-booking' ); ?>">
			</td>
			<td>
				<label class="screen-reader-text" for="<?php echo esc_attr( 'eb-service-duration-' . $index ); ?>"><?php esc_html_e( 'Duration in minutes', 'easy-booking' ); ?></label>
				<input type="number" class="small-text" id="<?php echo esc_attr( 'eb-service-duration-' . $index ); ?>" name="<?php echo esc_attr( $base . '[duration]' ); ?>" value="<?php echo esc_attr( $service['duration'] ); ?>" min="5" max="480" step="5">
			</td>
			<td>
				<label class="screen-reader-text" for="<?php echo esc_attr( 'eb-service-price-' . $index ); ?>"><?php esc_html_e( 'Price', 'easy-booking' ); ?></label>
				<input type="number" class="small-text" id="<?php echo esc_attr( 'eb-service-price-' . $index ); ?>" name="<?php echo esc_attr( $base . '[price]' ); ?>" value="<?php echo esc_attr( $service['price'] ); ?>" min="0" step="0.01">
			</td>
			<td>
				<button type="button" class="button-link eb-remove-service" aria-label="<?php esc_attr_e( 'Remove service', 'easy-booking' ); ?>">
					<span class="dashicons dashicons-trash" aria-hidden="true"></span>
				</button>
			</td>
		</tr>
		<?php
	}

	/**
	 * Working days checkboxes.
	 *
	 * @return void
	 */
	public function field_working_days() {
		global $wp_locale;

		$selected = array_map( 'intval', (array) Settings::get( 'working_days', array() ) );

		echo '<fieldset class="eb-days"><legend class="screen-reader-text">' . esc_html__( 'Working days', 'easy-booking' ) . '</legend>';

		// ISO day numbers (1 = Monday). WP_Locale uses 0 = Sunday.
		for ( $day = 1; $day <= 7; $day++ ) {
			printf(
				'<label class="eb-day"><input type="checkbox" name="%1$s[working_days][]" value="%2$d" %3$s> <span>%4$s</span></label>',
				esc_attr( Settings::OPTION_NAME ),
				absint( $day ),
				checked( in_array( $day, $selected, true ), true, false ),
				esc_html( $wp_locale->get_weekday_abbrev( $wp_locale->get_weekday( $day % 7 ) ) )
			);
		}

		echo '</fieldset>';
	}

	/**
	 * Working hours (start / end).
	 *
	 * @return void
	 */
	public function field_working_hours() {
		printf(
			'<label for="eb-start-time" class="screen-reader-text">%1$s</label><input type="time" id="eb-start-time" name="%2$s[start_time]" value="%3$s" required> <span aria-hidden="true">–</span> <label for="eb-end-time" class="screen-reader-text">%4$s</label><input type="time" id="eb-end-time" name="%2$s[end_time]" value="%5$s" required>',
			esc_html__( 'Opening time', 'easy-booking' ),
			esc_attr( Settings::OPTION_NAME ),
			esc_attr( Settings::get( 'start_time' ) ),
			esc_html__( 'Closing time', 'easy-booking' ),
			esc_attr( Settings::get( 'end_time' ) )
		);
	}

	/**
	 * Slot length select.
	 *
	 * @return void
	 */
	public function field_slot_length() {
		$current = (int) Settings::get( 'slot_length', 30 );

		printf( '<select id="eb-slot-length" name="%s[slot_length]">', esc_attr( Settings::OPTION_NAME ) );
		foreach ( Settings::SLOT_LENGTHS as $minutes ) {
			printf(
				'<option value="%1$d" %2$s>%3$s</option>',
				absint( $minutes ),
				selected( $current, $minutes, false ),
				/* translators: %d: number of minutes. */
				esc_html( sprintf( _n( '%d minute', '%d minutes', $minutes, 'easy-booking' ), $minutes ) )
			);
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'How often a new appointment can start (e.g. every 30 minutes).', 'easy-booking' ) . '</p>';
	}

	/**
	 * Booking window number field.
	 *
	 * @return void
	 */
	public function field_booking_window() {
		printf(
			'<input type="number" id="eb-booking-window" class="small-text" name="%1$s[booking_window]" value="%2$d" min="1" max="365"> %3$s',
			esc_attr( Settings::OPTION_NAME ),
			absint( Settings::get( 'booking_window', 60 ) ),
			esc_html__( 'days in advance', 'easy-booking' )
		);
	}

	/**
	 * Admin notification email field.
	 *
	 * @return void
	 */
	public function field_admin_email() {
		printf(
			'<input type="email" id="eb-admin-email" class="regular-text" name="%1$s[admin_email]" value="%2$s"><p class="description">%3$s</p>',
			esc_attr( Settings::OPTION_NAME ),
			esc_attr( Settings::get( 'admin_email' ) ),
			esc_html__( 'New booking notifications are sent to this address.', 'easy-booking' )
		);
	}

	/**
	 * Currency symbol field.
	 *
	 * @return void
	 */
	public function field_currency() {
		printf(
			'<input type="text" id="eb-currency" class="small-text" name="%1$s[currency]" value="%2$s" maxlength="5">',
			esc_attr( Settings::OPTION_NAME ),
			esc_attr( Settings::get( 'currency', '$' ) )
		);
	}

	/**
	 * Email subject + body fields for one template.
	 *
	 * @param array $args Field args (template key).
	 * @return void
	 */
	public function field_email_template( array $args ) {
		$key = $args['template'];
		$id  = 'eb-email-' . $key;

		printf(
			'<label for="%1$s-subject" class="eb-label">%2$s</label><input type="text" id="%1$s-subject" class="large-text" name="%3$s[%4$s_subject]" value="%5$s">',
			esc_attr( $id ),
			esc_html__( 'Subject', 'easy-booking' ),
			esc_attr( Settings::OPTION_NAME ),
			esc_attr( $key ),
			esc_attr( Settings::get( $key . '_subject' ) )
		);

		printf(
			'<label for="%1$s-body" class="eb-label">%2$s</label><textarea id="%1$s-body" class="large-text code" rows="8" name="%3$s[%4$s_body]">%5$s</textarea>',
			esc_attr( $id ),
			esc_html__( 'Message', 'easy-booking' ),
			esc_attr( Settings::OPTION_NAME ),
			esc_attr( $key ),
			esc_textarea( Settings::get( $key . '_body' ) )
		);
	}
}
