<?php
/**
 * Plugin bootstrap.
 *
 * @package MeetingRoomBooking
 */

namespace MRB\Core;

use MRB\Controllers\Admin\CalendarController;
use MRB\Controllers\Admin\ReservationActionController;
use MRB\Controllers\Admin\ReservationController;
use MRB\Controllers\Admin\SettingsController;
use MRB\Controllers\Front\BookingController;
use MRB\Controllers\Front\GuestReservationController;
use MRB\Controllers\Front\ManageReservationController;
use MRB\Support\FrontendNotice;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wires all WordPress hooks using objects resolved from the DI container.
 *
 * Nothing in this class contains business logic — it only registers hooks
 * and delegates every request to the appropriate controller.
 */
class Plugin {

	private Container $container;

	public function __construct( Container $container ) {
		$this->container = $container;
	}

	public function boot(): void {
		// Register asset handles.
		$this->container->get( Assets::class )->register();

		// Front-end notices (update / cancel / error banners).
		FrontendNotice::register();

		// ── Shortcodes ───────────────────────────────────────────────────────
		add_action( 'init', [ $this, 'registerShortcodes' ] );

		// ── Custom rewrite endpoint ──────────────────────────────────────────
		add_action( 'init',              [ $this, 'registerReservationEndpoint' ] );
		add_filter( 'query_vars',        [ $this, 'addQueryVars'                ] );
		add_action( 'template_redirect', [ $this, 'handleReservationRoute'      ] );

		// ── Admin menu pages ─────────────────────────────────────────────────
		add_action( 'admin_menu', [ $this, 'registerAdminPages' ] );

		// ── Admin POST handlers ──────────────────────────────────────────────
		/** @var ReservationActionController $action_ctrl */
		$action_ctrl = $this->container->get( ReservationActionController::class );
		add_action( 'admin_post_mrb_admin_update_reservation', [ $action_ctrl, 'handleUpdate'       ] );
		add_action( 'admin_post_mrb_change_status',            [ $action_ctrl, 'handleStatusChange' ] );

		/** @var SettingsController $settings_ctrl */
		$settings_ctrl = $this->container->get( SettingsController::class );
		add_action( 'admin_post_mrb_save_settings', [ $settings_ctrl, 'handleSave' ] );

		// ── Admin AJAX (calendar events) ─────────────────────────────────────
		/** @var CalendarController $calendar_ctrl */
		$calendar_ctrl = $this->container->get( CalendarController::class );
		add_action( 'admin_enqueue_scripts',          [ $calendar_ctrl, 'enqueueAssets'   ] );
		add_action( 'wp_ajax_mrb_get_calendar_events', [ $calendar_ctrl, 'handleGetEvents' ] );

		// ── Front-end booking form POST & AJAX ───────────────────────────────
		/** @var BookingController $booking_ctrl */
		$booking_ctrl = $this->container->get( BookingController::class );
		add_action( 'admin_post_mrb_submit_booking',              [ $booking_ctrl, 'handleSubmit'             ] );
		add_action( 'admin_post_nopriv_mrb_submit_booking',       [ $booking_ctrl, 'handleSubmit'             ] );
		add_action( 'wp_ajax_mrb_get_booked_times_range',         [ $booking_ctrl, 'handleGetBookedTimesRange'] );
		add_action( 'wp_ajax_nopriv_mrb_get_booked_times_range',  [ $booking_ctrl, 'handleGetBookedTimesRange'] );

		// ── Guest reservation actions (update / cancel) ──────────────────────
		/** @var GuestReservationController $guest_ctrl */
		$guest_ctrl = $this->container->get( GuestReservationController::class );
		add_action( 'admin_post_mrb_guest_update',        [ $guest_ctrl, 'handleUpdate' ] );
		add_action( 'admin_post_nopriv_mrb_guest_update', [ $guest_ctrl, 'handleUpdate' ] );
		add_action( 'admin_post_mrb_guest_cancel',        [ $guest_ctrl, 'handleCancel' ] );
		add_action( 'admin_post_nopriv_mrb_guest_cancel', [ $guest_ctrl, 'handleCancel' ] );
	}

	// ── Shortcodes ────────────────────────────────────────────────────────────

	public function registerShortcodes(): void {
		/** @var BookingController $booking_ctrl */
		$booking_ctrl = $this->container->get( BookingController::class );
		add_shortcode( 'mrb_booking_form', [ $booking_ctrl, 'renderShortcode' ] );
	}

	// ── Admin pages ───────────────────────────────────────────────────────────

	public function registerAdminPages(): void {
		/** @var ReservationController $res_ctrl */
		$res_ctrl = $this->container->get( ReservationController::class );
		add_menu_page(
			__( 'Meeting Bookings', 'meeting-room-booking' ),
			__( 'Meeting Bookings', 'meeting-room-booking' ),
			'manage_options',
			'mrb-reservations',
			[ $res_ctrl, 'dispatch' ],
			'dashicons-calendar-alt',
			26
		);

		/** @var CalendarController $calendar_ctrl */
		$calendar_ctrl = $this->container->get( CalendarController::class );
		add_submenu_page(
			'mrb-reservations',
			__( 'Calendar View', 'meeting-room-booking' ),
			__( 'Calendar View', 'meeting-room-booking' ),
			'manage_options',
			'mrb-calendar',
			[ $calendar_ctrl, 'show' ]
		);

		/** @var SettingsController $settings_ctrl */
		$settings_ctrl = $this->container->get( SettingsController::class );
		$settings_ctrl->register();
	}

	// ── Custom rewrite endpoint ───────────────────────────────────────────────

	public function registerReservationEndpoint(): void {
		add_rewrite_rule(
			'^reservation/([a-zA-Z0-9]+)/?$',
			'index.php?mrb_token=$matches[1]',
			'top'
		);
	}

	public function addQueryVars( array $vars ): array {
		$vars[] = 'mrb_token';
		return $vars;
	}

	public function handleReservationRoute(): void {
		$token = get_query_var( 'mrb_token' );
		if ( ! $token ) {
			return;
		}

		/** @var ManageReservationController $ctrl */
		$ctrl = $this->container->get( ManageReservationController::class );
		$ctrl->show( sanitize_text_field( (string) $token ) );
	}
}
