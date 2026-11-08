<?php
/**
 * File to handle the configuration to use only OVHcloud Object Storage.
 *
 * @package external-files-from-aws-s3
 */

namespace ExternalFilesFromAwsS3\Platforms\OvhcloudObjectStorage;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesFromAwsS3\Platforms\OvhcloudObjectStorage;

/**
 * Object to handle the configuration to use only OVHcloud Object Storage.
 */
class Configuration extends \ExternalFilesFromAwsS3\Platforms\S3Compatible\Configuration {
	/**
	 * The name of this configuration.
	 *
	 * @var string
	 */
	protected string $name = 'ovhcloud-object-storage';

	/**
	 * The class name of the platform.
	 *
	 * @var class-string<\ExternalFilesFromAwsS3\Platforms\S3Compatible>
	 */
	protected string $platform_class_name = OvhcloudObjectStorage::class;
}
