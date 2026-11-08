<?php
/**
 * File to handle the protocol of an S3-compatible storage.
 *
 * @package external-files-from-aws-s3
 */

namespace ExternalFilesFromAwsS3\Platforms\S3Compatible;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesFromAwsS3\Platform_Base;
use ExternalFilesFromAwsS3\Platforms\S3Compatible;
use ExternalFilesFromAwsS3\Protocol_Base;

/**
 * Object to handle the protocol of an S3-compatible storage.
 *
 * Presets extend this object and set their own name and platform class name.
 */
class Protocol extends Protocol_Base {
	/**
	 * The internal protocol name.
	 *
	 * @var string
	 */
	protected string $name = 's3-compatible';

	/**
	 * The class name of the platform.
	 *
	 * @var class-string<S3Compatible>
	 */
	protected string $platform_class_name = S3Compatible::class;

	/**
	 * Return the platform object.
	 *
	 * @return S3Compatible
	 */
	protected function get_platform(): S3Compatible {
		return $this->platform_class_name::get_instance();
	}

	/**
	 * Return the title of this protocol object.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return $this->get_platform()->get_label();
	}

	/**
	 * Check if URL is compatible with the given protocol.
	 *
	 * The URL must be the URL of the bucket or the public URL of the fields of this protocol
	 * or of the fields of the platform (e.g. from the global settings).
	 *
	 * @return bool
	 */
	public function is_url_compatible(): bool {
		// bail if the URL is from the fields of this protocol.
		if ( $this->get_platform()->is_url_of_fields( $this->get_url(), $this->get_fields() ) ) {
			return true;
		}

		// return whether the URL is from the fields of the platform.
		return $this->get_platform()->is_platform_url( $this->get_url() );
	}

	/**
	 * Return the corresponding "Platform_Base" object.
	 *
	 * @return Platform_Base|false
	 */
	protected function get_directory_listing_object(): Platform_Base|false {
		return $this->get_platform();
	}

	/**
	 * Return the key of a file by given URL.
	 *
	 * @param string $url The URL.
	 *
	 * @return string
	 */
	protected function get_key_of_file( string $url ): string {
		return $this->get_platform()->get_requested_url( $url, $this->get_fields() );
	}
}
