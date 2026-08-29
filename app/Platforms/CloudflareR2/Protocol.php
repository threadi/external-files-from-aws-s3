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
	protected string $name = 'cloudflare-r2';

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
		// accept the internal dashboard marker and any literal cloudflare.com URL.
		if ( str_contains( $this->get_url(), 'cloudflare.com' ) || str_starts_with( $this->get_url(), CloudflareR2::get_instance()->get_url_mark() ) ) {
			return true;
		}

		// return true if this is a public domain.
		return $this->is_public_domain_url();
	}

	/**
	 * Return whether this URL could change its hosting.
	 *
	 * @return bool
	 */
	public function can_change_hosting(): bool {
		return $this->is_public_domain_url();
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

		// strip the internal dashboard-marker prefix, if present.
		$dashboard_prefix = 'https://dash.cloudflare.com/' . $fields['account_id']['value'] . '/r2/' . ( $fields['eu']['value'] ? 'eu/' : '' ) . 'buckets/' . $fields['bucket']['value'] . '/objects/';
		if ( str_starts_with( $url, $dashboard_prefix ) ) {
			return str_replace( $dashboard_prefix, '', $url );
		}

		// otherwise strip the configured public domain prefix, if present.
		$domain = trim( (string) ( $fields['public_domain']['value'] ?? '' ) );
		if ( '' !== $domain ) {
			$domain = preg_replace( '/^https?:\/\//i', '', $domain );
			$domain = rtrim( (string) $domain, '/' );

			foreach ( array( 'https://' . $domain . '/', 'http://' . $domain . '/' ) as $public_prefix ) {
				if ( str_starts_with( $url, $public_prefix ) ) {
					return str_replace( $public_prefix, '', $url );
				}
			}
		}

		// nothing matched - return the URL unchanged.
		return $url;
	}

	/**
	 * Should be saved lokal if no public domain is used.
	 *
	 * @return bool
	 */
	public function should_be_saved_local(): bool {
		return ! $this->is_public_domain_url();
	}

	/**
	 * Return whether the used domain is a custom domain.
	 *
	 * @return bool
	 */
	private function is_public_domain_url(): bool {
		// get the configured public domain for this connection, if any.
		$fields = $this->get_fields();
		$domain = trim( (string) ( ! empty( $fields['public_domain']['value'] ) ? $fields['public_domain']['value'] : '' ) );

		// bail if no public domain is configured - nothing more to match against.
		if ( '' === $domain ) {
			return false;
		}

		// normalize the configured domain the same way CloudflareR2::build_public_url() does.
		$domain = preg_replace( '/^https?:\/\//i', '', $domain );
		$domain = rtrim( (string) $domain, '/' );

		// accept the URL if it points to that domain.
		return str_starts_with( $this->get_url(), 'https://' . $domain . '/' ) || str_starts_with( $this->get_url(), 'http://' . $domain . '/' );
	}
}
