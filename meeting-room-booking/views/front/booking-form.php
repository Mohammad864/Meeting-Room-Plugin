<?php
/**
 * Template: Front / Booking Form
 *
 * Variables (extracted by View::render):
 *   @var array|null $statusMessage  null, or ['type'=>string, 'token'=>string, 'link'=>string] / ['type'=>'error','message'=>string]
 *   @var string     $actionUrl      Escaped URL for the form action attribute.
 *
 * @package MeetingRoomBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<?php if ( null !== $statusMessage ) : ?>
	<?php if ( 'success' === $statusMessage['type'] ) : ?>
		<div class="mrb-notice mrb-notice-success mrb-success-card">
			<div class="mrb-success-icon">&#10003;</div>
			<div class="mrb-success-content">
				<strong><?php esc_html_e( 'Reservation submitted successfully.', 'meeting-room-booking' ); ?></strong>
				<p class="mrb-manage-link-label">
					<?php esc_html_e( 'Save this link to manage your booking later:', 'meeting-room-booking' ); ?>
				</p>
				<div class="mrb-manage-link-row">
					<a class="mrb-manage-link" id="mrb-manage-link" href="<?php echo esc_url( $statusMessage['link'] ); ?>">
						<?php echo esc_html( $statusMessage['link'] ); ?>
					</a>
					<button type="button" class="mrb-copy-link-btn" data-copy-target="#mrb-manage-link">
						<?php esc_html_e( 'Copy link', 'meeting-room-booking' ); ?>
					</button>
				</div>
				<p class="mrb-copy-feedback" id="mrb-copy-feedback" style="display:none;">
					<?php esc_html_e( 'Link copied.', 'meeting-room-booking' ); ?>
				</p>
			</div>
		</div>
	<?php elseif ( 'error' === $statusMessage['type'] ) : ?>
		<div class="mrb-notice mrb-notice-error mrb-error-card">
			<strong><?php esc_html_e( 'Could not submit reservation.', 'meeting-room-booking' ); ?></strong>
			<p><?php echo esc_html( $statusMessage['message'] ); ?></p>
		</div>
	<?php endif; ?>
<?php endif; ?>

<form method="post"
	  action="<?php echo $actionUrl; // phpcs:ignore WordPress.Security.EscapeOutput -- already escaped by controller ?>"
	  class="mrb-booking-form"
	  id="mrb-booking-form"
	  novalidate>

	<?php wp_nonce_field( 'mrb_submit_booking', 'mrb_nonce' ); ?>
	<input type="hidden" name="action"     value="mrb_submit_booking">
	<input type="hidden" name="start_time" id="mrb_start_time">
	<input type="hidden" name="end_time"   id="mrb_end_time">

	<div class="mrb-card mrb-booking-card">

		<div class="mrb-header mrb-booking-header">
			<div>
				<span class="mrb-eyebrow"><?php esc_html_e( 'Meeting room booking', 'meeting-room-booking' ); ?></span>
				<h2 class="mrb-title"><?php esc_html_e( 'Reserve a meeting room', 'meeting-room-booking' ); ?></h2>
				<p class="mrb-subtitle">
					<?php esc_html_e( 'Choose your meeting details, pick an available time, and submit your reservation.', 'meeting-room-booking' ); ?>
				</p>
			</div>
		</div>

		<div class="mrb-steps" aria-label="<?php esc_attr_e( 'Booking steps', 'meeting-room-booking' ); ?>">

			<button type="button" class="mrb-step is-active" data-target="meeting">
				<span>1</span>
				<strong><?php esc_html_e( 'Meeting', 'meeting-room-booking' ); ?></strong>
			</button>

			<button type="button" class="mrb-step" data-target="time">
				<span>2</span>
				<strong><?php esc_html_e( 'Time', 'meeting-room-booking' ); ?></strong>
			</button>

			<button type="button" class="mrb-step" data-target="contact">
				<span>3</span>
				<strong><?php esc_html_e( 'Contact', 'meeting-room-booking' ); ?></strong>
			</button>

		</div>

		<div id="mrb-form-feedback" class="mrb-form-feedback" style="display:none;"></div>

		<!-- Step 1: Meeting details -->
		<section id="mrb-section-meeting" class="mrb-section">
			<div class="mrb-section-header">
				<h3><?php esc_html_e( 'Meeting details', 'meeting-room-booking' ); ?></h3>
				<p><?php esc_html_e( 'Tell us what the reservation is for.', 'meeting-room-booking' ); ?></p>
			</div>

			<div class="mrb-field">
				<label for="mrb_meeting_title">
					<?php esc_html_e( 'Meeting Title', 'meeting-room-booking' ); ?>
					<span aria-hidden="true">*</span>
				</label>
				<input
					type="text"
					name="meeting_title"
					id="mrb_meeting_title"
					placeholder="<?php esc_attr_e( 'Example: Weekly planning meeting', 'meeting-room-booking' ); ?>"
					autocomplete="off"
					required>
			</div>

			<div class="mrb-field">
				<label for="mrb_description"><?php esc_html_e( 'Description', 'meeting-room-booking' ); ?></label>
				<textarea
					name="description"
					id="mrb_description"
					rows="4"
					placeholder="<?php esc_attr_e( 'Optional notes, agenda, equipment needs, or extra details.', 'meeting-room-booking' ); ?>"></textarea>
			</div>
		</section>

		<!-- Step 2: Date and time -->
		<section id="mrb-section-time" class="mrb-section">
			<div class="mrb-section-header">
				<h3><?php esc_html_e( 'Date and time', 'meeting-room-booking' ); ?></h3>
				<p><?php esc_html_e( 'Select a date, then choose an available time from the calendar.', 'meeting-room-booking' ); ?></p>
			</div>

			<div class="mrb-grid mrb-grid-date">
				<div class="mrb-field">
					<label for="mrb_meeting_date">
						<?php esc_html_e( 'Meeting Date', 'meeting-room-booking' ); ?>
						<span aria-hidden="true">*</span>
					</label>
					<input type="date" name="meeting_date" id="mrb_meeting_date" required>
				</div>

				<div class="mrb-field mrb-selected-time-field">
					<label>
						<?php esc_html_e( 'Selected Time', 'meeting-room-booking' ); ?>
						<span aria-hidden="true">*</span>
					</label>
					<div class="mrb-selected-time-box" id="mrb-selected-time-box">
						<div class="mrb-selected-time-icon">&#9203;</div>
						<div>
							<strong id="mrb-selected-time-text">
								<?php esc_html_e( 'No time selected yet', 'meeting-room-booking' ); ?>
							</strong>
							<span id="mrb-selected-time-help">
								<?php esc_html_e( 'Use the availability calendar to choose a free slot.', 'meeting-room-booking' ); ?>
							</span>
						</div>
					</div>
				</div>
			</div>

			<div class="mrb-time-actions">
				<button type="button" id="mrb-open-calendar" class="mrb-open-calendar-btn">
					<span aria-hidden="true">&#128197;</span>
					<?php esc_html_e( 'Choose available time', 'meeting-room-booking' ); ?>
				</button>

				<button type="button" id="mrb-clear-time" class="mrb-clear-time-btn" style="display:none;">
					<?php esc_html_e( 'Clear selected time', 'meeting-room-booking' ); ?>
				</button>
			</div>

			<div id="mrb-time-conflict-warning" class="mrb-time-conflict-warning" style="display:none;">
				<?php esc_html_e( 'The selected time overlaps with an existing reservation. Please choose an empty time.', 'meeting-room-booking' ); ?>
			</div>
		</section>

		<!-- Step 3: Contact information -->
		<section id="mrb-section-contact" class="mrb-section">
			<div class="mrb-section-header">
				<h3><?php esc_html_e( 'Contact information', 'meeting-room-booking' ); ?></h3>
				<p><?php esc_html_e( 'We will use this information to confirm your reservation.', 'meeting-room-booking' ); ?></p>
			</div>

			<div class="mrb-grid">

				<div class="mrb-field">
					<label for="mrb_first_name">
						<?php esc_html_e( 'First Name', 'meeting-room-booking' ); ?>
						<span aria-hidden="true">*</span>
					</label>
					<input
						type="text"
						name="first_name"
						id="mrb_first_name"
						placeholder="<?php esc_attr_e( 'First name', 'meeting-room-booking' ); ?>"
						autocomplete="given-name"
						required>
				</div>

				<div class="mrb-field">
					<label for="mrb_last_name">
						<?php esc_html_e( 'Last Name', 'meeting-room-booking' ); ?>
						<span aria-hidden="true">*</span>
					</label>
					<input
						type="text"
						name="last_name"
						id="mrb_last_name"
						placeholder="<?php esc_attr_e( 'Last name', 'meeting-room-booking' ); ?>"
						autocomplete="family-name"
						required>
				</div>

				<div class="mrb-field">
					<label for="mrb_email">
						<?php esc_html_e( 'Email', 'meeting-room-booking' ); ?>
						<span aria-hidden="true">*</span>
					</label>
					<input
						type="email"
						name="email"
						id="mrb_email"
						placeholder="<?php esc_attr_e( 'name@example.com', 'meeting-room-booking' ); ?>"
						autocomplete="email"
						required>
				</div>

				<div class="mrb-field">
					<label for="mrb_mobile">
						<?php esc_html_e( 'Mobile', 'meeting-room-booking' ); ?>
						<span aria-hidden="true">*</span>
					</label>
					<input
						type="tel"
						name="mobile"
						id="mrb_mobile"
						placeholder="<?php esc_attr_e( 'Your mobile number', 'meeting-room-booking' ); ?>"
						autocomplete="tel"
						required>
				</div>

			</div>
		</section>

		<div class="mrb-submit-area">
			<button type="submit" class="mrb-submit-btn" id="mrb-submit-btn">
				<span class="mrb-submit-label"><?php esc_html_e( 'Reserve Room', 'meeting-room-booking' ); ?></span>
				<span class="mrb-submit-loading" style="display:none;"><?php esc_html_e( 'Submitting&hellip;', 'meeting-room-booking' ); ?></span>
			</button>
			<p class="mrb-submit-note">
				<?php esc_html_e( 'Your reservation will be checked against existing bookings before it is confirmed.', 'meeting-room-booking' ); ?>
			</p>
		</div>

	</div><!-- .mrb-booking-card -->

	<!-- Availability calendar modal -->
	<div id="mrb-calendar-modal" class="mrb-calendar-modal" aria-hidden="true">

		<div class="mrb-calendar-modal-backdrop"></div>

		<div class="mrb-calendar-modal-panel"
			 role="dialog"
			 aria-modal="true"
			 aria-labelledby="mrb-calendar-modal-title">

			<div class="mrb-calendar-modal-header">
				<div>
					<h3 class="mrb-calendar-modal-title" id="mrb-calendar-modal-title">
						<?php esc_html_e( 'Choose reservation time', 'meeting-room-booking' ); ?>
					</h3>
					<p class="mrb-calendar-modal-subtitle">
						<?php esc_html_e( 'Drag on a free area to select your meeting time. Time precision is 30 minutes.', 'meeting-room-booking' ); ?>
					</p>
				</div>
				<button type="button"
						id="mrb-calendar-close"
						class="mrb-calendar-close-btn"
						aria-label="<?php esc_attr_e( 'Close calendar', 'meeting-room-booking' ); ?>">
					&times;
				</button>
			</div>

			<div id="mrb-calendar-error-box" class="mrb-calendar-error" style="display:none;"></div>

			<div class="mrb-calendar-layout">

				<aside class="mrb-calendar-days-panel">
					<div class="mrb-calendar-days-heading">
						<?php esc_html_e( 'Available days', 'meeting-room-booking' ); ?>
					</div>
					<div id="mrb-calendar-days" class="mrb-calendar-days">
						<?php esc_html_e( 'Loading days&hellip;', 'meeting-room-booking' ); ?>
					</div>
				</aside>

				<div class="mrb-calendar-timeline-panel">

					<div class="mrb-calendar-current-day">
						<div>
							<strong id="mrb-current-day-title">
								<?php esc_html_e( 'Select a date', 'meeting-room-booking' ); ?>
							</strong>
							<span id="mrb-current-day-subtitle"></span>
						</div>
						<div id="mrb-current-selection-label" class="mrb-current-selection-label">
							<?php esc_html_e( 'Drag to select', 'meeting-room-booking' ); ?>
						</div>
					</div>

					<div id="mrb-calendar-loading" class="mrb-calendar-loading" style="display:none;">
						<?php esc_html_e( 'Loading availability&hellip;', 'meeting-room-booking' ); ?>
					</div>

					<div id="mrb-google-timeline-scroll" class="mrb-google-timeline-scroll">
						<div id="mrb-google-timeline" class="mrb-google-timeline">
							<div id="mrb-timeline-labels"  class="mrb-timeline-labels"></div>
							<div id="mrb-timeline-grid"    class="mrb-timeline-grid">
								<div id="mrb-booked-layer"    class="mrb-booked-layer"></div>
								<div id="mrb-selection-layer" class="mrb-selection-layer"></div>
							</div>
						</div>
					</div>

					<div class="mrb-calendar-help">
						<span class="mrb-help-item">
							<span class="mrb-dot mrb-dot-booked"></span>
							<?php esc_html_e( 'Booked', 'meeting-room-booking' ); ?>
						</span>
						<span class="mrb-help-item">
							<span class="mrb-dot mrb-dot-selected"></span>
							<?php esc_html_e( 'Your selection', 'meeting-room-booking' ); ?>
						</span>
						<span class="mrb-help-item">
							<span class="mrb-dot mrb-dot-free"></span>
							<?php esc_html_e( 'Free', 'meeting-room-booking' ); ?>
						</span>
					</div>

				</div>

			</div>

		</div>

	</div><!-- .mrb-calendar-modal -->

</form>
