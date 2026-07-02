<?php
/**
 * Front-end view: manage reservation page (/reservation/{token}/).
 *
 * Variables extracted by View::output():
 *   @var array  $reservation  Reservation row including room_name from repository JOIN.
 *   @var string $token        Guest edit token.
 *   @var bool   $can_manage   Whether the guest can still edit/cancel this reservation.
 *
 * @package MeetingRoomBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use MRB\Enums\ReservationStatus;
use MRB\Support\FrontendNotice;

$status    = isset( $reservation['status'] ) ? sanitize_key( $reservation['status'] ) : '';
$room_name = ! empty( $reservation['room_name'] )
	? $reservation['room_name']
	: ( ! empty( $reservation['room_id'] )
		/* translators: %d: Room ID number. */
		? sprintf( __( 'Room #%d', 'meeting-room-booking' ), (int) $reservation['room_id'] )
		: '' );

get_header();
?>
<main class="mrb-container">

	<?php FrontendNotice::render(); ?>

	<header class="mrb-page-header">
		<h1 class="mrb-page-title"><?php esc_html_e( 'Manage Your Reservation', 'meeting-room-booking' ); ?></h1>
		<p class="mrb-page-subtitle">
			<?php esc_html_e( 'Review your booking details below. You can update the information or cancel the reservation if needed.', 'meeting-room-booking' ); ?>
		</p>
	</header>

	<!-- Reservation details card -->
	<section class="mrb-card">
		<div class="mrb-card-header">
			<h2 class="mrb-card-title"><?php esc_html_e( 'Reservation Details', 'meeting-room-booking' ); ?></h2>
		</div>

		<div class="mrb-details-grid">

			<div class="mrb-detail-item">
				<span class="mrb-detail-label"><?php esc_html_e( 'Name', 'meeting-room-booking' ); ?></span>
				<span class="mrb-detail-value">
					<?php echo esc_html( trim( ( $reservation['first_name'] ?? '' ) . ' ' . ( $reservation['last_name'] ?? '' ) ) ); ?>
				</span>
			</div>

			<div class="mrb-detail-item">
				<span class="mrb-detail-label"><?php esc_html_e( 'Email', 'meeting-room-booking' ); ?></span>
				<span class="mrb-detail-value"><?php echo esc_html( $reservation['email'] ?? '' ); ?></span>
			</div>

			<div class="mrb-detail-item">
				<span class="mrb-detail-label"><?php esc_html_e( 'Mobile', 'meeting-room-booking' ); ?></span>
				<span class="mrb-detail-value"><?php echo esc_html( $reservation['mobile'] ?? '' ); ?></span>
			</div>

			<div class="mrb-detail-item">
				<span class="mrb-detail-label"><?php esc_html_e( 'Meeting Title', 'meeting-room-booking' ); ?></span>
				<span class="mrb-detail-value"><?php echo esc_html( $reservation['meeting_title'] ?? '' ); ?></span>
			</div>

			<?php if ( $room_name ) : ?>
				<div class="mrb-detail-item">
					<span class="mrb-detail-label"><?php esc_html_e( 'Room', 'meeting-room-booking' ); ?></span>
					<span class="mrb-detail-value"><?php echo esc_html( $room_name ); ?></span>
				</div>
			<?php endif; ?>

			<div class="mrb-detail-item">
				<span class="mrb-detail-label"><?php esc_html_e( 'Date', 'meeting-room-booking' ); ?></span>
				<span class="mrb-detail-value"><?php echo esc_html( $reservation['meeting_date'] ?? '' ); ?></span>
			</div>

			<div class="mrb-detail-item">
				<span class="mrb-detail-label"><?php esc_html_e( 'Time', 'meeting-room-booking' ); ?></span>
				<span class="mrb-detail-value">
					<?php echo esc_html( ( $reservation['start_time'] ?? '' ) . ' – ' . ( $reservation['end_time'] ?? '' ) ); ?>
				</span>
			</div>

			<div class="mrb-detail-item">
				<span class="mrb-detail-label"><?php esc_html_e( 'Status', 'meeting-room-booking' ); ?></span>
				<span class="mrb-detail-value">
					<span class="mrb-status-badge mrb-status-<?php echo esc_attr( $status ); ?>">
						<?php echo esc_html( ReservationStatus::label( $status ) ); ?>
					</span>
				</span>
			</div>

			<?php if ( ! empty( $reservation['description'] ) ) : ?>
				<div class="mrb-detail-item mrb-detail-item-full">
					<span class="mrb-detail-label"><?php esc_html_e( 'Description', 'meeting-room-booking' ); ?></span>
					<span class="mrb-detail-value"><?php echo esc_html( $reservation['description'] ); ?></span>
				</div>
			<?php endif; ?>

		</div>
	</section>

	<?php if ( $can_manage ) : ?>

		<!-- Edit reservation card -->
		<section class="mrb-card">
			<div class="mrb-card-header">
				<h2 class="mrb-card-title"><?php esc_html_e( 'Edit Reservation', 'meeting-room-booking' ); ?></h2>
			</div>

			<div class="mrb-card-actions">
				<button
					type="button"
					class="mrb-btn mrb-btn-primary"
					onclick="document.getElementById('mrb-edit-form-panel').style.display='block';this.style.display='none';">
					<?php esc_html_e( 'Edit Reservation', 'meeting-room-booking' ); ?>
				</button>
			</div>

			<div id="mrb-edit-form-panel" class="mrb-panel" style="display:none;">
				<h3 class="mrb-panel-title"><?php esc_html_e( 'Update Reservation Details', 'meeting-room-booking' ); ?></h3>

				<form method="post"
					  action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
					  class="mrb-form">

					<input type="hidden" name="action" value="mrb_guest_update">
					<input type="hidden" name="token"  value="<?php echo esc_attr( $token ); ?>">
					<?php wp_nonce_field( 'mrb_guest_update_' . $token, 'mrb_nonce' ); ?>

					<div class="mrb-form-grid">

						<div class="mrb-form-group">
							<label for="mrb_first_name"><?php esc_html_e( 'First Name', 'meeting-room-booking' ); ?></label>
							<input id="mrb_first_name" class="mrb-input" type="text" name="first_name"
								value="<?php echo esc_attr( $reservation['first_name'] ?? '' ); ?>" required>
						</div>

						<div class="mrb-form-group">
							<label for="mrb_last_name"><?php esc_html_e( 'Last Name', 'meeting-room-booking' ); ?></label>
							<input id="mrb_last_name" class="mrb-input" type="text" name="last_name"
								value="<?php echo esc_attr( $reservation['last_name'] ?? '' ); ?>" required>
						</div>

						<div class="mrb-form-group">
							<label for="mrb_email"><?php esc_html_e( 'Email', 'meeting-room-booking' ); ?></label>
							<input id="mrb_email" class="mrb-input" type="email" name="email"
								value="<?php echo esc_attr( $reservation['email'] ?? '' ); ?>" required>
						</div>

						<div class="mrb-form-group">
							<label for="mrb_mobile"><?php esc_html_e( 'Mobile', 'meeting-room-booking' ); ?></label>
							<input id="mrb_mobile" class="mrb-input" type="text" name="mobile"
								value="<?php echo esc_attr( $reservation['mobile'] ?? '' ); ?>" required>
						</div>

						<div class="mrb-form-group mrb-form-group-full">
							<label for="mrb_meeting_title"><?php esc_html_e( 'Meeting Title', 'meeting-room-booking' ); ?></label>
							<input id="mrb_meeting_title" class="mrb-input" type="text" name="meeting_title"
								value="<?php echo esc_attr( $reservation['meeting_title'] ?? '' ); ?>" required>
						</div>

						<div class="mrb-form-group">
							<label for="mrb_meeting_date"><?php esc_html_e( 'Date', 'meeting-room-booking' ); ?></label>
							<input id="mrb_meeting_date" class="mrb-input" type="date" name="meeting_date"
								value="<?php echo esc_attr( $reservation['meeting_date'] ?? '' ); ?>" required>
						</div>

						<div class="mrb-form-group">
							<label for="mrb_start_time"><?php esc_html_e( 'Start Time', 'meeting-room-booking' ); ?></label>
							<input id="mrb_start_time" class="mrb-input" type="time" name="start_time"
								value="<?php echo esc_attr( substr( $reservation['start_time'] ?? '', 0, 5 ) ); ?>" required>
						</div>

						<div class="mrb-form-group">
							<label for="mrb_end_time"><?php esc_html_e( 'End Time', 'meeting-room-booking' ); ?></label>
							<input id="mrb_end_time" class="mrb-input" type="time" name="end_time"
								value="<?php echo esc_attr( substr( $reservation['end_time'] ?? '', 0, 5 ) ); ?>" required>
						</div>

						<div class="mrb-form-group mrb-form-group-full">
							<label for="mrb_description"><?php esc_html_e( 'Description', 'meeting-room-booking' ); ?></label>
							<textarea id="mrb_description" class="mrb-textarea" name="description" rows="5"
							><?php echo esc_textarea( $reservation['description'] ?? '' ); ?></textarea>
						</div>

					</div><!-- .mrb-form-grid -->

					<div class="mrb-form-actions">
						<button type="submit" class="mrb-btn mrb-btn-success">
							<?php esc_html_e( 'Save Changes', 'meeting-room-booking' ); ?>
						</button>
						<button
							type="button"
							class="mrb-btn"
							onclick="document.getElementById('mrb-edit-form-panel').style.display='none';">
							<?php esc_html_e( 'Cancel Edit', 'meeting-room-booking' ); ?>
						</button>
					</div>

				</form>
			</div><!-- #mrb-edit-form-panel -->
		</section>

		<!-- Cancel reservation card -->
		<section class="mrb-card mrb-danger-panel">
			<div class="mrb-card-header">
				<h2 class="mrb-card-title"><?php esc_html_e( 'Cancel Reservation', 'meeting-room-booking' ); ?></h2>
			</div>
			<p class="mrb-panel-text">
				<?php esc_html_e( 'If you no longer need this booking, you can cancel it here. This action cannot be undone.', 'meeting-room-booking' ); ?>
			</p>
			<form method="post"
				  action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
				  onsubmit="return confirm('<?php echo esc_js( __( 'Are you sure you want to cancel this reservation? This action cannot be undone.', 'meeting-room-booking' ) ); ?>');">
				<input type="hidden" name="action" value="mrb_guest_cancel">
				<input type="hidden" name="token"  value="<?php echo esc_attr( $token ); ?>">
				<?php wp_nonce_field( 'mrb_guest_cancel_' . $token, 'mrb_nonce' ); ?>
				<button type="submit" class="mrb-btn mrb-btn-danger">
					<?php esc_html_e( 'Cancel Reservation', 'meeting-room-booking' ); ?>
				</button>
			</form>
		</section>

	<?php endif; ?>

</main>
<?php
get_footer();
