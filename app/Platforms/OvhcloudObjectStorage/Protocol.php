<?php
/**
 * File to handle the protocol of OVHcloud Object Storage.
 *
 * @package external-files-from-aws-s3
 */

namespace ExternalFilesFromAwsS3\Platforms\OvhcloudObjectStorage;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesFromAwsS3\Platforms\OvhcloudObjectStorage;

/**
 * Object to handle the protocol of OVHcloud Object Storage.
 */
class Protocol extends \ExternalFilesFromAwsS3\Platforms\S3Compatible\Protocol {
	/**
	 * The internal protocol name.
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
