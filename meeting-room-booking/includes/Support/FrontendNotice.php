<?php
/**
 * Front-end notice renderer.
 *
 * @package MeetingRoomBooking
 */

namespace MRB\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders a dismissible notice on front-end reservation pages based on
 * URL query parameters (?updated=1, ?cancelled=1, ?error=<code>).
 *
 * Rendered once per request via a static flag to prevent duplication
 * when both wp_body_open and wp_footer fire in the same request.
 */
class FrontendNotice {

	/** Prevent rendering the same notice more than once per page load. */
	private static bool $rendered = false;

	/**
	 * Register WordPress hooks.
	 *
	 * Called once by Plugin::boot(). Uses wp_body_open (preferred) and
	 * wp_footer as a fallback for themes that do not call wp_body_open.
	 */
	public static function register(): void {
		add_action( 'wp_body_open', [ self::class, 'render' ], 5 );
		add_action( 'wp_footer',    [ self::class, 'render' ], 5 );
	}

	/**
	 * Output the notice HTML when a relevant query param is present.
	 *
	 * Safe to call multiple times — renders only once per request.
	 */
	public static function render(): void {
		if ( is_admin() || self::$rendered ) {
			return;
		}

		$updated   = isset( $_GET['updated'] )   ? sanitize_key( wp_unslash( $_GET['updated'] ) )   : '';
		$cancelled = isset( $_GET['cancelled'] ) ? sanitize_key( wp_unslash( $_GET['cancelled'] ) ) : '';
		$error     = isset( $_GET['error'] )     ? sanitize_key( wp_unslash( $_GET['error'] ) )     : '';

		if ( '1' !== $updated && '1' !== $cancelled && '' === $error ) {
			return;
		}

		self::$rendered = true;

		?>
		<div class="mrb-user-notice-wrap">
			<?php if ( '1' === $updated ) : ?>
				<div class="mrb-user-notice mrb-user-notice-success" role="alert">
					<?php esc_html_e( 'Your reservation has been updated successfully.', 'meeting-room-booking' ); ?>
				</div>
			<?php elseif ( '1' === $cancelled ) : ?>
				<div class="mrb-user-notice mrb-user-notice-success" role="alert">
					<?php esc_html_e( 'Your reservation has been cancelled successfully.', 'meeting-room-booking' ); ?>
				</div>
			<?php elseif ( '' !== $error ) : ?>
				<div class="mrb-user-notice mrb-user-notice-error" role="alert">
					<?php echo esc_html( ErrorMessages::get( $error ) ); ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/** Private constructor — this class is not meant to be instantiated. */
	private function __construct() {}
}
