<?php
/**
 * This file contains a helper object for this plugin.
 *
 * @package external-files-from-aws-s3
 */

namespace ExternalFilesFromAwsS3\Plugin;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Initialize the helper for this plugin.
 */
class Helper {

	/**
	 * Return plugin dir of this plugin with trailing slash.
	 *
	 * @return string
	 */
	public static function get_plugin_dir(): string {
		return trailingslashit( plugin_dir_path( EFMLAWSS3_PLUGIN ) );
	}

	/**
	 * Return plugin URL of this plugin with trailing slash.
	 *
	 * @return string
	 */
	public static function get_plugin_url(): string {
		return trailingslashit( plugin_dir_url( EFMLAWSS3_PLUGIN ) );
	}

	/**
	 * Return the version of the given file.
	 *
	 * With WP_DEBUG or plugin-debug enabled its @filemtime().
	 * Without this it is the plugin-version.
	 *
	 * @param string $filepath The absolute path to the requested file.
	 *
	 * @return string
	 */
	public static function get_file_version( string $filepath ): string {
		// check for WP_DEBUG.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			return (string) filemtime( $filepath );
		}

		$plugin_version = EFMLAWSS3_PLUGIN;

		/**
		 * Filter the used file version (for JS- and CSS-files, which get enqueued).
		 *
		 * @since 1.0.0 Available since 1.0.0.
		 *
		 * @param string $plugin_version The plugin-version.
		 * @param string $filepath The absolute path to the requested file.
		 */
		return apply_filters( 'efmlawss3_enqueued_file_version', $plugin_version, $filepath );
	}
}
