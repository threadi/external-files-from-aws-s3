<?php
/**
 * File to handle the export of files to IONOS Object Storage.
 *
 * @package external-files-from-aws-s3
 */

namespace ExternalFilesFromAwsS3\Platforms\IonosObjectStorage;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesFromAwsS3\Platforms\IonosObjectStorage;

/**
 * Object to handle the export of files to IONOS Object Storage.
 */
class Export extends \ExternalFilesFromAwsS3\Platforms\S3Compatible\Export {
	/**
	 * The class name of the platform.
	 *
	 * @var class-string<\ExternalFilesFromAwsS3\Platforms\S3Compatible>
	 */
	protected string $platform_class_name = IonosObjectStorage::class;

	/**
	 * Instance of actual object.
	 *
	 * @var ?\ExternalFilesFromAwsS3\Platforms\S3Compatible\Export
	 */
	protected static ?\ExternalFilesFromAwsS3\Platforms\S3Compatible\Export $instance = null;
}
