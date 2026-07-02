<?php
/**
 * Plugin Name:       Meeting Room Booking
 * Plugin URI:        https://example.com/meeting-room-booking
 * Description:       Lets visitors reserve meeting rooms from the front end, with admin approval, automatic room allocation, conflict detection, and email notifications.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Mohammad Taghipoor
 * Author URI:        https://example.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       meeting-room-booking
 * Domain Path:       /languages
 *
 * @package MeetingRoomBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MRB_VERSION',         '1.0.0' );
define( 'MRB_PLUGIN_FILE',     __FILE__ );
define( 'MRB_PLUGIN_DIR',      plugin_dir_path( __FILE__ ) );
define( 'MRB_PLUGIN_URL',      plugin_dir_url( __FILE__ ) );
define( 'MRB_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Autoloader: MRB\Foo\Bar → includes/Foo/Bar.php
spl_autoload_register( static function ( string $class ): void {
	$prefix = 'MRB\\';

	if ( strpos( $class, $prefix ) !== 0 ) {
		return;
	}

	$file = MRB_PLUGIN_DIR
		. 'includes/'
		. str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) )
		. '.php';

	if ( file_exists( $file ) ) {
		require_once $file;
	}
} );

register_activation_hook( __FILE__,   [ \MRB\Core\Activator::class, 'activate'   ] );
register_deactivation_hook( __FILE__, [ \MRB\Core\Activator::class, 'deactivate' ] );

add_action( 'plugins_loaded', static function (): void {
	load_plugin_textdomain(
		'meeting-room-booking',
		false,
		dirname( MRB_PLUGIN_BASENAME ) . '/languages'
	);

	$container = \MRB\Core\Container::build();
	( new \MRB\Core\Plugin( $container ) )->boot();
} );
