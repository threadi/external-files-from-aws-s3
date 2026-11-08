<?php
/**
 * File to handle the protocol of Wasabi.
 *
 * @package external-files-from-aws-s3
 */

namespace ExternalFilesFromAwsS3\Platforms\Wasabi;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesFromAwsS3\Platforms\Wasabi;

/**
 * Object to handle the protocol of Wasabi.
 */
class Protocol extends \ExternalFilesFromAwsS3\Platforms\S3Compatible\Protocol {
	/**
	 * The internal protocol name.
	 *
	 * @var string
	 */
	protected string $name = 'wasabi';

	/**
	 * The class name of the platform.
	 *
	 * @var class-string<\ExternalFilesFromAwsS3\Platforms\S3Compatible>
	 */
	protected string $platform_class_name = Wasabi::class;
}
