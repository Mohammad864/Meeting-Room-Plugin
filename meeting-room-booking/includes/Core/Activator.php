<?php
/**
 * Plugin activation and deactivation routines.
 *
 * @package MeetingRoomBooking
 */

namespace MRB\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles database schema creation and rewrite-rule flushing on
 * activation / deactivation.
 */
class Activator {

	/**
	 * Run on plugin activation.
	 *
	 * Creates database tables, seeds default options, synchronises the
	 * room rows to the configured count, and flushes rewrite rules.
	 */
	public static function activate(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate    = $wpdb->get_charset_collate();
		$rooms_table        = $wpdb->prefix . 'mrb_rooms';
		$reservations_table = $wpdb->prefix . 'mrb_reservations';

		// 1. Rooms table.
		dbDelta( "CREATE TABLE {$rooms_table} (
			id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name       VARCHAR(191)    NOT NULL,
			created_at DATETIME        NOT NULL,
			PRIMARY KEY (id)
		) {$charset_collate};" );

		// 2. Reservations table.
		dbDelta( "CREATE TABLE {$reservations_table} (
			id            BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
			first_name    VARCHAR(100)     NOT NULL,
			last_name     VARCHAR(100)     NOT NULL,
			mobile        VARCHAR(30)      NOT NULL,
			email         VARCHAR(191)     NOT NULL,
			meeting_title VARCHAR(191)     NOT NULL,
			meeting_date  DATE             NOT NULL,
			start_time    TIME             NOT NULL,
			end_time      TIME             NOT NULL,
			description   TEXT             NULL,
			room_id       BIGINT UNSIGNED  NULL,
			status        VARCHAR(20)      NOT NULL DEFAULT 'pending',
			created_at    DATETIME         NOT NULL,
			updated_at    DATETIME         NULL,
			edit_token    VARCHAR(64)      NOT NULL,
			cancelled_at  DATETIME         NULL,
			PRIMARY KEY  (id),
			KEY edit_token_idx   (edit_token),
			KEY meeting_date_idx (meeting_date),
			KEY status_idx       (status),
			KEY date_status_idx  (meeting_date, status),
			KEY room_id_idx      (room_id),
			CONSTRAINT fk_mrb_room
				FOREIGN KEY (room_id) REFERENCES {$rooms_table}(id)
				ON DELETE SET NULL
		) {$charset_collate};" );

		// 3. Seed default options.
		if ( false === get_option( 'mrb_number_of_rooms', false ) ) {
			update_option( 'mrb_number_of_rooms', 3, true );
		}

		// 4. Sync room rows to configured count.
		self::syncRoomsToConfiguredCount();

		// 5. Register rewrite rule and flush.
		add_rewrite_rule(
			'^reservation/([a-zA-Z0-9]+)/?$',
			'index.php?mrb_token=$matches[1]',
			'top'
		);
		flush_rewrite_rules();
	}

	/**
	 * Run on plugin deactivation.
	 *
	 * Flushes rewrite rules so the /reservation/{token}/ route is removed
	 * cleanly and does not leave stale entries in the rewrite cache.
	 */
	public static function deactivate(): void {
		flush_rewrite_rules();
	}

	/**
	 * Synchronise the mrb_rooms table with the configured room count.
	 *
	 * Safe to call during activation and from the Settings page.
	 * - Adds rows when the desired count is higher than current.
	 * - Removes surplus rows only when they have no associated reservations.
	 */
	public static function syncRoomsToConfiguredCount(): void {
		global $wpdb;

		$rooms_table        = $wpdb->prefix . 'mrb_rooms';
		$reservations_table = $wpdb->prefix . 'mrb_reservations';

		$desired_count = max( 1, (int) get_option( 'mrb_number_of_rooms', 3 ) );
		$existing      = $wpdb->get_results(
			"SELECT id FROM {$rooms_table} ORDER BY id ASC",
			ARRAY_A
		) ?: [];

		$current_count = count( $existing );
		$now           = current_time( 'mysql' );

		if ( $current_count < $desired_count ) {
			$to_add = $desired_count - $current_count;
			for ( $i = 1; $i <= $to_add; $i++ ) {
				$wpdb->insert(
					$rooms_table,
					[
						'name'       => 'Room ' . ( $current_count + $i ),
						'created_at' => $now,
					],
					[ '%s', '%s' ]
				);
			}
		} elseif ( $current_count > $desired_count ) {
			$extras = array_slice( $existing, $desired_count );
			foreach ( $extras as $room ) {
				$room_id = (int) $room['id'];
				$in_use  = (int) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM {$reservations_table} WHERE room_id = %d",
						$room_id
					)
				);
				if ( 0 === $in_use ) {
					$wpdb->delete( $rooms_table, [ 'id' => $room_id ], [ '%d' ] );
				}
			}
		}
	}
}
