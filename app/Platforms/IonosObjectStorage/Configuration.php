<?php
/**
 * File to handle the configuration to use only IONOS Object Storage.
 *
 * @package external-files-from-aws-s3
 */

namespace ExternalFilesFromAwsS3\Platforms\IonosObjectStorage;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesFromAwsS3\Platforms\IonosObjectStorage;

/**
 * Object to handle the configuration to use only IONOS Object Storage.
 */
class Configuration extends \ExternalFilesFromAwsS3\Platforms\S3Compatible\Configuration {
	/**
	 * The name of this configuration.
	 *
	 * @var string
	 */
	protected string $name = 'ionos-object-storage';

	/**
	 * The class name of the platform.
	 *
	 * @var class-string<\ExternalFilesFromAwsS3\Platforms\S3Compatible>
	 */
	protected string $platform_class_name = IonosObjectStorage::class;
}
