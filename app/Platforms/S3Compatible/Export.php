<?php
/**
 * File to handle the export of files to an S3-compatible storage.
 *
 * @package external-files-from-aws-s3
 */

namespace ExternalFilesFromAwsS3\Platforms\S3Compatible;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesFromAwsS3\Export_Base;
use ExternalFilesFromAwsS3\Platform_Base;
use ExternalFilesFromAwsS3\Platforms\S3Compatible;

/**
 * Object to handle the export of files to an S3-compatible storage.
 *
 * Presets extend this object and set their own platform class name and the static property $instance.
 */
class Export extends Export_Base {
	/**
	 * The class name of the platform.
	 *
	 * @var class-string<S3Compatible>
	 */
	protected string $platform_class_name = S3Compatible::class;

	/**
	 * Instance of actual object.
	 *
	 * Each preset must declare this property, too, to get its own instance.
	 *
	 * @var ?Export
	 */
	protected static ?Export $instance = null;

	/**
	 * Constructor, not used as this a Singleton object.
	 */
	protected function __construct() {}

	/**
	 * Prevent cloning of this object.
	 *
	 * @return void
	 */
	private function __clone() {}

	/**
	 * Return instance of this object as singleton.
	 *
	 * @return static
	 */
	public static function get_instance(): static {
		if ( ! static::$instance instanceof static ) {
			static::$instance = new static();
		}

		return static::$instance;
	}

	/**
	 * Export a file to this service. Returns the external URL if it was successfully and false if not.
	 *
	 * Failed checks of the public availability are logged with all details during the export.
	 *
	 * @param int                 $attachment_id The attachment ID.
	 * @param string              $target The target.
	 * @param array<string,mixed> $credentials The credentials.
	 * @return string|bool
	 */
	public function export_file( int $attachment_id, string $target, array $credentials ): string|bool {
		// get the platform.
		$platform = $this->platform_class_name::get_instance();

		// enable the detailed log.
		$platform->set_public_check_logging( true );

		// run the export.
		try {
			return parent::export_file( $attachment_id, $target, $credentials );
		} finally {
			// disable the detailed log.
			$platform->set_public_check_logging( false );
		}
	}

	/**
	 * Return the corresponding "Platform_Base" object.
	 *
	 * @return Platform_Base|false
	 */
	protected function get_directory_listing_object(): Platform_Base|false {
		return $this->platform_class_name::get_instance();
	}
}
