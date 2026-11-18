<?php
/**
 * File to handle the export of files to Scaleway Object Storage.
 *
 * @package external-files-from-aws-s3
 */

namespace ExternalFilesFromAwsS3\Platforms\ScalewayObjectStorage;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesFromAwsS3\Platforms\ScalewayObjectStorage;

/**
 * Object to handle the export of files to Scaleway Object Storage.
 */
class Export extends \ExternalFilesFromAwsS3\Platforms\S3Compatible\Export {
	/**
	 * The class name of the platform.
	 *
	 * @var class-string<\ExternalFilesFromAwsS3\Platforms\S3Compatible>
	 */
	protected string $platform_class_name = ScalewayObjectStorage::class;

	/**
	 * Instance of actual object.
	 *
	 * @var ?\ExternalFilesFromAwsS3\Platforms\S3Compatible\Export
	 */
	protected static ?\ExternalFilesFromAwsS3\Platforms\S3Compatible\Export $instance = null;
}
