<?php
/**
 * Booking form template.
 *
 * Variables provided by Shortcode::render():
 *
 * @var array  $services Configured services.
 * @var string $selected Pre-selected service ID.
 * @var string $min_date Earliest bookable date (Y-m-d).
 * @var string $max_date Latest bookable date (Y-m-d).
 * @var string $title    Form title.
 * @var string $uid      Unique ID prefix for this form instance.
 *
 * @package EasyBooking
 */

use EasyBooking\Settings;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template is included inside a class method, so variables are not global.
?>
<div class="eb-booking" id="<?php echo esc_attr( $uid ); ?>">
	<?php if ( '' !== $title ) : ?>
		<h2 class="eb-booking__title"><?php echo esc_html( $title ); ?></h2>
	<?php endif; ?>

	<?php if ( empty( $services ) ) : ?>
		<p class="eb-notice eb-notice--info"><?php esc_html_e( 'Online booking is not available at the moment.', 'easy-booking' ); ?></p>
	<?php else : ?>
		<form class="eb-form" method="post" novalidate>
			<div class="eb-alert" role="status" aria-live="polite" hidden></div>

			<div class="eb-grid">
				<div class="eb-field">
					<label for="<?php echo esc_attr( $uid ); ?>-name"><?php esc_html_e( 'Full name', 'easy-booking' ); ?> <span class="eb-required" aria-hidden="true">*</span></label>
					<input type="text" id="<?php echo esc_attr( $uid ); ?>-name" name="customer_name" autocomplete="name" maxlength="191" required>
					<span class="eb-error" data-error-for="customer_name"></span>
				</div>

				<div class="eb-field">
					<label for="<?php echo esc_attr( $uid ); ?>-email"><?php esc_html_e( 'Email', 'easy-booking' ); ?> <span class="eb-required" aria-hidden="true">*</span></label>
					<input type="email" id="<?php echo esc_attr( $uid ); ?>-email" name="customer_email" autocomplete="email" maxlength="191" required>
					<span class="eb-error" data-error-for="customer_email"></span>
				</div>

				<div class="eb-field">
					<label for="<?php echo esc_attr( $uid ); ?>-phone"><?php esc_html_e( 'Phone', 'easy-booking' ); ?> <span class="eb-required" aria-hidden="true">*</span></label>
					<input type="tel" id="<?php echo esc_attr( $uid ); ?>-phone" name="customer_phone" autocomplete="tel" maxlength="50" required>
					<span class="eb-error" data-error-for="customer_phone"></span>
				</div>

				<div class="eb-field">
					<label for="<?php echo esc_attr( $uid ); ?>-service"><?php esc_html_e( 'Service', 'easy-booking' ); ?> <span class="eb-required" aria-hidden="true">*</span></label>
					<select id="<?php echo esc_attr( $uid ); ?>-service" name="service_id" required>
						<option value=""><?php esc_html_e( 'Select a service', 'easy-booking' ); ?></option>
						<?php foreach ( $services as $service ) : ?>
							<option value="<?php echo esc_attr( $service['id'] ); ?>" <?php selected( $selected, $service['id'] ); ?>>
								<?php
								echo esc_html(
									sprintf(
										/* translators: 1: service name, 2: duration in minutes, 3: formatted price. */
										__( '%1$s — %2$d min — %3$s', 'easy-booking' ),
										$service['name'],
										$service['duration'],
										Settings::format_price( $service['price'] )
									)
								);
								?>
							</option>
						<?php endforeach; ?>
					</select>
					<span class="eb-error" data-error-for="service_id"></span>
				</div>

				<div class="eb-field">
					<label for="<?php echo esc_attr( $uid ); ?>-date"><?php esc_html_e( 'Date', 'easy-booking' ); ?> <span class="eb-required" aria-hidden="true">*</span></label>
					<input type="date" id="<?php echo esc_attr( $uid ); ?>-date" name="booking_date" min="<?php echo esc_attr( $min_date ); ?>" max="<?php echo esc_attr( $max_date ); ?>" required>
					<span class="eb-error" data-error-for="booking_date"></span>
				</div>
			</div>

			<fieldset class="eb-field eb-slots-field">
				<legend><?php esc_html_e( 'Available times', 'easy-booking' ); ?> <span class="eb-required" aria-hidden="true">*</span></legend>
				<div class="eb-slots" data-slots>
					<p class="eb-slots__hint"><?php esc_html_e( 'Choose a service and a date to see available times.', 'easy-booking' ); ?></p>
				</div>
				<span class="eb-error" data-error-for="start_time"></span>
			</fieldset>

			<div class="eb-field">
				<label for="<?php echo esc_attr( $uid ); ?>-notes"><?php esc_html_e( 'Notes', 'easy-booking' ); ?> <span class="eb-optional"><?php esc_html_e( '(optional)', 'easy-booking' ); ?></span></label>
				<textarea id="<?php echo esc_attr( $uid ); ?>-notes" name="notes" rows="3" maxlength="1000"></textarea>
				<span class="eb-error" data-error-for="notes"></span>
			</div>

			<?php // Honeypot: hidden from humans, bots tend to fill it in. ?>
			<div class="eb-hp" aria-hidden="true">
				<label for="<?php echo esc_attr( $uid ); ?>-website"><?php esc_html_e( 'Leave this field empty', 'easy-booking' ); ?></label>
				<input type="text" id="<?php echo esc_attr( $uid ); ?>-website" name="eb_website" tabindex="-1" autocomplete="off">
			</div>

			<button type="submit" class="eb-button"><?php esc_html_e( 'Book appointment', 'easy-booking' ); ?></button>
		</form>
	<?php endif; ?>
</div>
