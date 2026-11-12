<?php
/**
 * File, which handles the DigitalOcean Spaces support as own protocol.
 *
 * @package external-files-from-aws-s3
 */

namespace ExternalFilesFromAwsS3\Platforms\DigitalOceanSpaces;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesFromAwsS3\Platform_Base;
use ExternalFilesFromAwsS3\Platforms\DigitalOceanSpaces;
use ExternalFilesFromAwsS3\Protocol_Base;

/**
 * Object to handle the protocol for DigitalOcean Spaces.
 */
class Protocol extends Protocol_Base {
	/**
	 * The internal protocol name.
	 *
	 * @var string
	 */
	protected string $name = 'digital-ocean-spaces';

	/**
	 * Return the title of this protocol object.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return DigitalOceanSpaces::get_instance()->get_label(); // @phpstan-ignore method.notFound
	}

	/**
	 * Check if URL is compatible with the given protocol.
	 *
	 * @return bool
	 */
	public function is_url_compatible(): bool {
		// bail if this is not a DigitalOcean Space URL.
		if ( str_contains( $this->get_url(), 'digitaloceanspaces.com' ) ) {
			return true;
		}

		// return whether the URL is on the custom domain.
		return '' !== DigitalOceanSpaces::get_instance()->get_key_from_custom_domain_url( $this->get_url(), $this->get_fields() );
	}

	/**
	 * Return the corresponding "Platform_Base" object.
	 *
	 * @return Platform_Base|false
	 */
	protected function get_directory_listing_object(): Platform_Base|false {
		return DigitalOceanSpaces::get_instance();
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

		// return the key, if the URL is on the custom domain.
		$key = DigitalOceanSpaces::get_instance()->get_key_from_custom_domain_url( $url, $fields );
		if ( '' !== $key ) {
			return $key;
		}

		// return the key.
		return str_replace( 'https://' . $fields['bucket']['value'] . '.' . $fields['region']['value'] . '.digitaloceanspaces.com/', '', $url );
	}
}
