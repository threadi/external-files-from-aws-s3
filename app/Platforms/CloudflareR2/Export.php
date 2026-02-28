<?php
/**
 * File to handle export tasks for Cloudflare R2.
 *
 * @package external-files-from-aws-s3
 */

namespace ExternalFilesFromAwsS3\Platforms\CloudflareR2;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesFromAwsS3\Export_Base;
use ExternalFilesFromAwsS3\Platform_Base;
use ExternalFilesFromAwsS3\Platforms\CloudflareR2;

/**
 * Object for export files to Cloudflare R2.
 */
class Export extends Export_Base {
	/**
	 * Instance of actual object.
	 *
	 * @var Export|null
	 */
	private static ?Export $instance = null;

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
	 * @return Export
	 */
	public static function get_instance(): Export {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Return the corresponding "Platform_Base" object.
	 *
	 * @return Platform_Base|false
	 */
	protected function get_directory_listing_object(): Platform_Base|false {
		return CloudflareR2::get_instance();
	}
}
