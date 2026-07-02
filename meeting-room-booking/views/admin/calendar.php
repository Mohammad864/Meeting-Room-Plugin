<?php
/**
 * Admin view: reservation calendar page.
 *
 * Rendered by CalendarController::show(). No variables are passed in.
 *
 * @package MeetingRoomBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap mrb-admin-wrap">

	<div class="mrb-admin-page-header">
		<div>
			<h1 class="mrb-admin-page-title">
				<?php esc_html_e( 'Reservation Calendar', 'meeting-room-booking' ); ?>
			</h1>
			<p class="mrb-admin-page-subtitle">
				<?php esc_html_e( 'View and manage meeting room reservations in a calendar layout.', 'meeting-room-booking' ); ?>
			</p>
		</div>
	</div>

	<div class="mrb-admin-card">
		<div class="mrb-admin-card-body">

			<div class="mrb-calendar-legend">
				<strong class="mrb-calendar-legend-title">
					<?php esc_html_e( 'Legend:', 'meeting-room-booking' ); ?>
				</strong>
				<span class="mrb-badge mrb-badge-pending"><?php esc_html_e( 'Pending',   'meeting-room-booking' ); ?></span>
				<span class="mrb-badge mrb-badge-confirmed"><?php esc_html_e( 'Approved', 'meeting-room-booking' ); ?></span>
				<span class="mrb-badge mrb-badge-rejected"><?php esc_html_e( 'Rejected',  'meeting-room-booking' ); ?></span>
				<span class="mrb-badge mrb-badge-cancelled"><?php esc_html_e( 'Cancelled','meeting-room-booking' ); ?></span>
			</div>

			<div class="mrb-filter-bar">
				<div class="mrb-filter-group">
					<label for="mrb-calendar-status-filter">
						<?php esc_html_e( 'Status', 'meeting-room-booking' ); ?>
					</label>
					<select id="mrb-calendar-status-filter" class="mrb-admin-select">
						<option value=""><?php esc_html_e( 'All Statuses', 'meeting-room-booking' ); ?></option>
						<?php foreach ( \MRB\Enums\ReservationStatus::ALL as $s ) : ?>
							<option value="<?php echo esc_attr( $s ); ?>">
								<?php echo esc_html( \MRB\Enums\ReservationStatus::label( $s ) ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>

			<div class="mrb-calendar-loading" aria-live="polite">
				<?php esc_html_e( 'Loading calendar&hellip;', 'meeting-room-booking' ); ?>
			</div>

			<div id="mrb-calendar" class="mrb-admin-calendar"></div>

		</div>
	</div>

</div>
