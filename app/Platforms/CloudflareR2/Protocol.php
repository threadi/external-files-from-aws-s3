<?php
/**
 * File, which handles the Cloudflare R2 support as own protocol.
 *
 * @package external-files-from-aws-s3
 */

namespace ExternalFilesFromAwsS3\Platforms\CloudflareR2;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesFromAwsS3\Platform_Base;
use ExternalFilesFromAwsS3\Platforms\CloudflareR2;
use ExternalFilesFromAwsS3\Protocol_Base;

/**
 * Object to handle the protocol for Cloudflare R2.
 */
class Protocol extends Protocol_Base {
	/**
	 * The internal protocol name.
	 *
	 * @var string
	 */
	protected string $name = 'cloudflare-2';

	/**
	 * Return the title of this protocol object.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return CloudflareR2::get_instance()->get_label(); // @phpstan-ignore method.notFound
	}

	/**
	 * Check if URL is compatible with the given protocol.
	 *
	 * @return bool
	 */
	public function is_url_compatible(): bool {
		// bail if this is not a Cloudflare R2 URL.
		return ! ( ! str_contains( $this->get_url(), 'cloudflare.com' ) && ! str_starts_with( $this->get_url(), CloudflareR2::get_instance()->get_url_mark() ) );
	}

	/**
	 * Return the corresponding "Platform_Base" object.
	 *
	 * @return Platform_Base|false
	 */
	protected function get_directory_listing_object(): Platform_Base|false {
		return CloudflareR2::get_instance();
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
		return str_replace( 'https://dash.cloudflare.com/' . $fields['account_id']['value'] . '/r2/' . ( $fields['eu']['value'] ? 'eu/' : '' ) . 'buckets/' . $fields['bucket']['value'] . '/objects/', '', $url );
	}
}
