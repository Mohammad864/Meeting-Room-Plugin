<?php
/**
 * Reservation status constants.
 *
 * @package MeetingRoomBooking
 */

namespace MRB\Enums;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Acts as a PHP 7.4-compatible enum for reservation status values.
 *
 * Using a class with constants instead of a PHP 8.1 native enum ensures
 * the plugin remains compatible with WordPress's minimum supported PHP version.
 */
class ReservationStatus {

	const PENDING   = 'pending';
	const APPROVED  = 'approved';
	const REJECTED  = 'rejected';
	const CANCELLED = 'cancelled';

	/** All valid status values — used for iteration in views. */
	const ALL = [
		self::PENDING,
		self::APPROVED,
		self::REJECTED,
		self::CANCELLED,
	];

	/** Statuses the admin can set via the quick-change action. */
	const ADMIN_SETTABLE = [
		self::PENDING,
		self::APPROVED,
		self::REJECTED,
	];

	/** Statuses after which a guest can no longer edit or cancel. */
	const LOCKED = [
		self::CANCELLED,
		self::REJECTED,
	];

	// ── Helpers ───────────────────────────────────────────────────────────────

	public static function isValid( string $status ): bool {
		return in_array( $status, self::ALL, true );
	}

	public static function isAdminSettable( string $status ): bool {
		return in_array( $status, self::ADMIN_SETTABLE, true );
	}

	public static function isLocked( string $status ): bool {
		return in_array( $status, self::LOCKED, true );
	}

	/**
	 * Return a human-readable, translatable label for a status value.
	 *
	 * @param  string $status One of the class constants.
	 * @return string         Translated label.
	 */
	public static function label( string $status ): string {
		$labels = [
			self::PENDING   => __( 'Pending',   'meeting-room-booking' ),
			self::APPROVED  => __( 'Approved',  'meeting-room-booking' ),
			self::REJECTED  => __( 'Rejected',  'meeting-room-booking' ),
			self::CANCELLED => __( 'Cancelled', 'meeting-room-booking' ),
		];

		return $labels[ $status ] ?? ucfirst( $status );
	}

	/** Private constructor — this class is not meant to be instantiated. */
	private function __construct() {}
}
