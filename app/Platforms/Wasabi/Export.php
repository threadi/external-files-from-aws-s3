<?php
/**
 * File to handle the export of files to Wasabi.
 *
 * @package external-files-from-aws-s3
 */

namespace ExternalFilesFromAwsS3\Platforms\Wasabi;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesFromAwsS3\Platforms\Wasabi;

/**
 * Object to handle the export of files to Wasabi.
 */
class Export extends \ExternalFilesFromAwsS3\Platforms\S3Compatible\Export {
	/**
	 * The class name of the platform.
	 *
	 * @var class-string<\ExternalFilesFromAwsS3\Platforms\S3Compatible>
	 */
	protected string $platform_class_name = Wasabi::class;

	/**
	 * Instance of actual object.
	 *
	 * @var ?\ExternalFilesFromAwsS3\Platforms\S3Compatible\Export
	 */
	protected static ?\ExternalFilesFromAwsS3\Platforms\S3Compatible\Export $instance = null;
}
