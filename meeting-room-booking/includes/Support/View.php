<?php
/**
 * Template rendering helper.
 *
 * @package MeetingRoomBooking
 */

namespace MRB\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Minimal view renderer.
 *
 * Resolves templates under MRB_PLUGIN_DIR/views/, extracts data variables
 * into the template scope, and either returns the output as a string or
 * echoes it directly.
 */
class View {

	/**
	 * Render a template and return its output as a string.
	 *
	 * @param  string               $template Relative template path without extension (e.g. 'front/booking-form').
	 * @param  array<string, mixed> $data     Variables to extract into the template scope.
	 * @return string               Rendered HTML.
	 *
	 * @throws \RuntimeException When the template file does not exist.
	 */
	public static function render( string $template, array $data = [] ): string {
		$file = self::resolve( $template );

		ob_start();
		self::load( $file, $data );
		return (string) ob_get_clean();
	}

	/**
	 * Render a template and echo its output immediately.
	 *
	 * @param  string               $template Relative template path without extension.
	 * @param  array<string, mixed> $data     Variables to extract into the template scope.
	 *
	 * @throws \RuntimeException When the template file does not exist.
	 */
	public static function output( string $template, array $data = [] ): void {
		$file = self::resolve( $template );
		self::load( $file, $data );
	}

	// ── Private helpers ───────────────────────────────────────────────────────

	/**
	 * Resolve a template name to an absolute file path.
	 *
	 * @param  string $template Relative template path without extension.
	 * @return string           Absolute file path.
	 *
	 * @throws \RuntimeException When the template file does not exist.
	 */
	private static function resolve( string $template ): string {
		// Guard against directory traversal — only allow alphanumeric chars, hyphens, and slashes.
		$template = preg_replace( '/[^a-z0-9\-\/]/i', '', $template );

		$file = MRB_PLUGIN_DIR . 'views/' . $template . '.php';

		if ( ! file_exists( $file ) ) {
			throw new \RuntimeException(
				sprintf( '[MRB] Template not found: %s', esc_html( $template ) )
			);
		}

		return $file;
	}

	/**
	 * Load a template file with extracted variables.
	 *
	 * Uses EXTR_SKIP to prevent data from overwriting existing locals
	 * (including $this or superglobals).
	 *
	 * @param  string               $file Absolute path to the template file.
	 * @param  array<string, mixed> $data Variables to expose inside the template.
	 */
	private static function load( string $file, array $data ): void {
		// Prevent data keys from shadowing superglobals or reserved names.
		unset( $data['GLOBALS'], $data['_SERVER'], $data['_GET'], $data['_POST'],
			$data['_COOKIE'], $data['_FILES'], $data['_ENV'], $data['_REQUEST'],
			$data['_SESSION'], $data['this'] );

		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		extract( $data, EXTR_SKIP );

		include $file;
	}

	/** Private constructor — this class is not meant to be instantiated. */
	private function __construct() {}
}
