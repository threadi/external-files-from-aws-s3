<?php
/**
 * File to handle the protocol of Hetzner Object Storage.
 *
 * @package external-files-from-aws-s3
 */

namespace ExternalFilesFromAwsS3\Platforms\HetznerObjectStorage;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesFromAwsS3\Platforms\HetznerObjectStorage;

/**
 * Object to handle the protocol of Hetzner Object Storage.
 */
class Protocol extends \ExternalFilesFromAwsS3\Platforms\S3Compatible\Protocol {
	/**
	 * The internal protocol name.
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
