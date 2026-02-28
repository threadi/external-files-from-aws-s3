<?php
/**
 * File, which handles the AWS S3 support as own protocol.
 *
 * @package external-files-from-aws-s3
 */

namespace ExternalFilesFromAwsS3\Platforms\AwsS3;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesFromAwsS3\Platform_Base;
use ExternalFilesFromAwsS3\Platforms\AwsS3;
use ExternalFilesFromAwsS3\Protocol_Base;

/**
 * Object to handle the protocol for AWS S3.
 */
class Protocol extends Protocol_Base {
	/**
	 * The internal protocol name.
	 *
	 * @var string
	 */
	protected string $name = 'aws-s3';

	/**
	 * Return the title of this protocol object.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return AwsS3::get_instance()->get_label(); // @phpstan-ignore method.notFound
	}

	/**
	 * Check if URL is compatible with the given protocol.
	 *
	 * @return bool
	 */
	public function is_url_compatible(): bool {
		// bail if this is not an AWS S3 URL.
		return ! ( ! str_contains( $this->get_url(), 'amazonaws.com' ) && ! str_starts_with( $this->get_url(), AwsS3::get_instance()->get_url_mark( '' ) ) );
	}

	/**
	 * Return the corresponding "Platform_Base" object.
	 *
	 * @return Platform_Base|false
	 */
	protected function get_directory_listing_object(): Platform_Base|false {
		return AwsS3::get_instance();
	}

	/**
	 * Return the key of a file by given URL.
	 *
	 * @param string $url The URL.
	 *
	 * @return string
	 */
	protected function get_key_of_file( string $url ): string {
		// get the fields.
		$fields = $this->get_fields();

		// return the key.
		return str_replace( sprintf( 'https://%s.s3.%s.amazonaws.com/', $fields['bucket']['value'], $fields['region']['value'] ), '', $url );
	}
}
