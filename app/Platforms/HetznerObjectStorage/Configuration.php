<?php
/**
 * File to handle the configuration to use only Hetzner Object Storage.
 *
 * @package external-files-from-aws-s3
 */

namespace ExternalFilesFromAwsS3\Platforms\HetznerObjectStorage;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesFromAwsS3\Platforms\HetznerObjectStorage;

/**
 * Object to handle the configuration to use only Hetzner Object Storage.
 */
class Configuration extends \ExternalFilesFromAwsS3\Platforms\S3Compatible\Configuration {
	/**
	 * The name of this configuration.
	 *
	 * @var string
	 */
	protected string $name = 'hetzner-object-storage';

	/**
	 * The class name of the platform.
	 *
	 * @var class-string<\ExternalFilesFromAwsS3\Platforms\S3Compatible>
	 */
	protected string $platform_class_name = HetznerObjectStorage::class;
}
