<?php
/**
 * This file contains the uninstall-handling for this plugin.
 *
 * @package external-files-from-aws-s3
 */

namespace ExternalFilesFromAwsS3\Plugin;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Uninstall this plugin.
 */
class Uninstall {

	/**
	 * Instance of actual object.
	 *
	 * @var ?Uninstall
	 */
	private static ?Uninstall $instance = null;

	/**
	 * Constructor, not used as this a Singleton object.
	 */
	private function __construct() {}

	/**
	 * Prevent cloning of this object.
	 *
	 * @return void
	 */
	private function __clone() {}

	/**
	 * Return instance of this object as singleton.
	 *
	 * @return Uninstall
	 */
	public static function get_instance(): Uninstall {
		if ( is_null( self::$instance ) ) {
			self::$instance = new static();
		}

		return self::$instance;
	}

	/**
	 * Run uninstallation of this plugin.
	 *
	 * @return void
	 */
	public function run(): void {
		// remove custom transients.
		delete_transient( 'efml_aws_s3_regions' );
		delete_site_transient( 'efmlawss3_latest_release' );

		// remove the settings for the import limit, which are not used anymore since 2.0.0.
		foreach ( array( 'eml_s3_import_limit', 'eml_cloudflare_r2_import_limit', 'eml_digital_ocean_spaces_import_limit', 'eml_backblaze_s3_import_limit' ) as $option ) {
			delete_option( $option );
		}
	}
}
