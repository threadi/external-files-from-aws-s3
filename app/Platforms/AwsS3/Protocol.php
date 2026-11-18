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
		// return true if this is an AWS S3 URL.
		if ( str_contains( $this->get_url(), 'amazonaws.com' ) || str_starts_with( $this->get_url(), AwsS3::get_instance()->get_url_mark( '' ) ) ) {
			return true;
		}

		// return whether this is a URL of the configured CloudFront domain.
		return $this->is_cloudfront_url();
	}

	/**
	 * Return whether this URL could change its hosting.
	 *
	 * This is possible for files delivered via CloudFront, as they are public available there.
	 *
	 * @return bool
	 */
	public function can_change_hosting(): bool {
		return $this->is_cloudfront_url();
	}

	/**
	 * Files delivered via CloudFront should not be saved locally.
	 *
	 * @return bool
	 */
	public function should_be_saved_local(): bool {
		return ! $this->is_cloudfront_url();
	}

	/**
	 * Return whether the URL of this object uses the configured CloudFront domain.
	 *
	 * Only if credentials with a bucket are given, as the file is loaded via the S3 API. Without them,
	 * the CloudFront URL is handled by the HTTP protocol of the main plugin as any other public URL.
	 *
	 * @return bool
	 */
	private function is_cloudfront_url(): bool {
		// get the fields.
		$fields = $this->get_fields();

		// bail if no bucket is given.
		if ( empty( $fields['bucket']['value'] ) ) {
			return false;
		}

		// return whether the URL uses the configured CloudFront domain.
		return AwsS3::get_instance()->is_cloudfront_url( $this->get_url(), $fields );
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

		// return the key from a CloudFront URL.
		if ( AwsS3::get_instance()->is_cloudfront_url( $url, $fields ) ) {
			return AwsS3::get_instance()->get_key_from_cloudfront_url( $url, $fields );
		}

		// return the key.
		return str_replace( sprintf( 'https://%s.s3.%s.amazonaws.com/', $fields['bucket']['value'], $fields['region']['value'] ), '', $url );
	}
}
