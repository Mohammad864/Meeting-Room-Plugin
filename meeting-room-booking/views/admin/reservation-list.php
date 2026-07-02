<?php
/**
 * Admin view: reservation list page.
 *
 * Variables extracted by View::output():
 *   @var array  $reservations    Array of reservation rows from the repository.
 *   @var array  $filters         Active filter values: search, date, status.
 *   @var int    $minimumRooms    Minimum simultaneous rooms needed on $calculationDate.
 *   @var string $calculationDate Date used for the minimum-rooms calculation (Y-m-d).
 *
 * @package MeetingRoomBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use MRB\Support\StatusBadge;

$notice_message = isset( $_GET['mrb_message'] ) ? sanitize_key( wp_unslash( $_GET['mrb_message'] ) ) : '';
$notice_error   = isset( $_GET['mrb_error'] )   ? sanitize_key( wp_unslash( $_GET['mrb_error'] ) )   : '';
?>
<div class="wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Meeting Room Reservations', 'meeting-room-booking' ); ?></h1>

	<?php if ( 'status_updated' === $notice_message || 'updated' === $notice_message ) : ?>
		<?php
		$msg = ( 'status_updated' === $notice_message && isset( $_GET['mrb_msg'] ) )
			? sanitize_text_field( wp_unslash( rawurldecode( $_GET['mrb_msg'] ) ) )
			: __( 'Reservation updated successfully.', 'meeting-room-booking' );
		?>
		<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $msg ); ?></p></div>
	<?php endif; ?>

	<?php if ( 'status_failed' === $notice_error || 'update_failed' === $notice_error ) : ?>
		<?php
		$err_msg = isset( $_GET['mrb_error_msg'] )
			? sanitize_text_field( wp_unslash( rawurldecode( $_GET['mrb_error_msg'] ) ) )
			: __( 'Operation failed. Please try again.', 'meeting-room-booking' );
		?>
		<div class="notice notice-error is-dismissible"><p><?php echo esc_html( $err_msg ); ?></p></div>
	<?php endif; ?>

	<hr class="wp-header-end">

	<div style="background:#fff;border:1px solid #ccd0d4;padding:12px 16px;margin:16px 0;border-radius:4px;">
		<strong>
			<?php
			echo esc_html( sprintf(
				/* translators: 1: number of rooms required, 2: date string. */
				__( 'Minimum simultaneous rooms required on %2$s: %1$d', 'meeting-room-booking' ),
				$minimumRooms,
				$calculationDate
			) );
			?>
		</strong>
	</div>

	<!-- Filter form -->
	<form method="get" style="margin:16px 0 24px;">
		<input type="hidden" name="page" value="mrb-reservations">

		<input
			type="search"
			name="s"
			value="<?php echo esc_attr( $filters['search'] ?? '' ); ?>"
			placeholder="<?php esc_attr_e( 'Search name or mobile', 'meeting-room-booking' ); ?>">

		<input
			type="date"
			name="meeting_date"
			value="<?php echo esc_attr( $filters['date'] ?? '' ); ?>">

		<select name="status">
			<option value=""><?php esc_html_e( 'All Statuses', 'meeting-room-booking' ); ?></option>
			<?php foreach ( \MRB\Enums\ReservationStatus::ALL as $s ) : ?>
				<option value="<?php echo esc_attr( $s ); ?>" <?php selected( $filters['status'] ?? '', $s ); ?>>
					<?php echo esc_html( \MRB\Enums\ReservationStatus::label( $s ) ); ?>
				</option>
			<?php endforeach; ?>
		</select>

		<button type="submit" class="button"><?php esc_html_e( 'Filter', 'meeting-room-booking' ); ?></button>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=mrb-reservations' ) ); ?>" class="button">
			<?php esc_html_e( 'Reset', 'meeting-room-booking' ); ?>
		</a>
	</form>

	<!-- Reservations table -->
	<table class="widefat fixed striped">
		<thead>
			<tr>
				<th width="50"><?php esc_html_e( 'ID',            'meeting-room-booking' ); ?></th>
				<th><?php esc_html_e( 'Name',          'meeting-room-booking' ); ?></th>
				<th><?php esc_html_e( 'Mobile',        'meeting-room-booking' ); ?></th>
				<th><?php esc_html_e( 'Email',         'meeting-room-booking' ); ?></th>
				<th><?php esc_html_e( 'Meeting Title', 'meeting-room-booking' ); ?></th>
				<th><?php esc_html_e( 'Date',          'meeting-room-booking' ); ?></th>
				<th><?php esc_html_e( 'Time',          'meeting-room-booking' ); ?></th>
				<th><?php esc_html_e( 'Room',          'meeting-room-booking' ); ?></th>
				<th><?php esc_html_e( 'Status',        'meeting-room-booking' ); ?></th>
				<th width="220"><?php esc_html_e( 'Actions', 'meeting-room-booking' ); ?></th>
			</tr>
		</thead>
		<tbody>

		<?php if ( empty( $reservations ) ) : ?>
			<tr>
				<td colspan="10"><?php esc_html_e( 'No reservations found.', 'meeting-room-booking' ); ?></td>
			</tr>
		<?php else : ?>
			<?php foreach ( $reservations as $row ) :
				$id        = (int) $row['id'];
				$full_name = trim( ( $row['first_name'] ?? '' ) . ' ' . ( $row['last_name'] ?? '' ) );
				$room_label = ! empty( $row['room_name'] )
					? $row['room_name']
					: ( ! empty( $row['room_id'] )
						/* translators: %d: Room ID number. */
						? sprintf( __( 'Room #%d', 'meeting-room-booking' ), (int) $row['room_id'] )
						: '—' );

				$edit_url    = admin_url( 'admin.php?page=mrb-reservations&action=edit&id=' . $id );
				$approve_url = wp_nonce_url(
					admin_url( 'admin-post.php?action=mrb_change_status&id=' . $id . '&status=approved' ),
					'mrb_change_status_' . $id
				);
				$reject_url  = wp_nonce_url(
					admin_url( 'admin-post.php?action=mrb_change_status&id=' . $id . '&status=rejected' ),
					'mrb_change_status_' . $id
				);
				$pending_url = wp_nonce_url(
					admin_url( 'admin-post.php?action=mrb_change_status&id=' . $id . '&status=pending' ),
					'mrb_change_status_' . $id
				);
			?>
			<tr>
				<td><?php echo esc_html( $id ); ?></td>
				<td><strong><?php echo esc_html( $full_name ); ?></strong></td>
				<td><?php echo esc_html( $row['mobile'] ?? '' ); ?></td>
				<td>
					<?php if ( ! empty( $row['email'] ) ) : ?>
						<a href="mailto:<?php echo esc_attr( $row['email'] ); ?>"><?php echo esc_html( $row['email'] ); ?></a>
					<?php else : ?>—<?php endif; ?>
				</td>
				<td><?php echo esc_html( $row['meeting_title'] ?? '' ); ?></td>
				<td><?php echo esc_html( $row['meeting_date']  ?? '' ); ?></td>
				<td><?php echo esc_html( ( $row['start_time'] ?? '' ) . ' – ' . ( $row['end_time'] ?? '' ) ); ?></td>
				<td><?php echo esc_html( $room_label ); ?></td>
				<td>
					<?php
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- StatusBadge::render() returns pre-escaped HTML
					echo StatusBadge::render( $row['status'] ?? 'pending' );
					?>
				</td>
				<td>
					<a class="button button-small" href="<?php echo esc_url( $edit_url );    ?>"><?php esc_html_e( 'Edit',    'meeting-room-booking' ); ?></a>
					<a class="button button-small" href="<?php echo esc_url( $approve_url ); ?>"><?php esc_html_e( 'Approve', 'meeting-room-booking' ); ?></a>
					<a class="button button-small" href="<?php echo esc_url( $reject_url );  ?>"><?php esc_html_e( 'Reject',  'meeting-room-booking' ); ?></a>
					<a class="button button-small" href="<?php echo esc_url( $pending_url ); ?>"><?php esc_html_e( 'Pending', 'meeting-room-booking' ); ?></a>
				</td>
			</tr>
			<?php endforeach; ?>
		<?php endif; ?>

		</tbody>
	</table>
</div>
