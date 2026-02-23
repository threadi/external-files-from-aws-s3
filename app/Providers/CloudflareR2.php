<?php
/**
 * File to handle the base options for Cloudflare R2.
 *
 * @package external-files-from-aws-s3
 */

namespace ExternalFilesFromAwsS3\Providers;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesFromAwsS3\Provider_Base;

/**
 * Object for export files to Cloudflare R2.
 */
class CloudflareR2 extends Provider_Base {
	/**
	 * Set the internal name.
	 *
	 * @var string
	 */
	protected string $name = 'cloudflare-r2';

	/**
	 * Instance of actual object.
	 *
	 * @var ?CloudflareR2
	 */
	private static ?CloudflareR2 $instance = null;

	/**
	 * Constructor, not used as this a Singleton object.
	 */
	private function __construct() {}

	/**
	 * Prevent cloning of this object.
	 *
	 * @return void
	 */
	private function __clone() {}

	/**
	 * Return instance of this object as singleton.
	 *
	 * @return CloudflareR2
	 */
	public static function get_instance(): CloudflareR2 {
		if ( is_null( self::$instance ) ) {
			self::$instance = new static();
		}

		return self::$instance;
	}

	/**
	 * Return the public label.
	 *
	 * @return string
	 */
	public function get_label(): string {
		return 'Cloudflare R2';
	}

	/**
	 * Initialize the provider.
	 *
	 * @return void
	 */
	public function init(): void {
		add_filter( 'efmlawss3_aws_client_configuration', array( $this, 'add_configuration' ), 10, 2 );
	}

	/**
	 * Set configuration for the S3Client object.
	 *
	 * @param array<string,mixed> $configuration The configuration for the S3 Client object.
	 * @param array<string,mixed> $fields Our own fields.
	 *
	 * @return array<string,mixed>
	 */
	public function add_configuration( array $configuration, array $fields ): array {
		// bail if not account ID is given.
		if( empty( $fields['account_id']['value'] ) ) {
			return $configuration;
		}

		// get the endpoint depending on setting.
		$endpoint = 'https://' . $fields['account_id']['value'] . '.r2.cloudflarestorage.com';
		if( $fields['r2_eu']['value'] ) {
			$endpoint = 'https://' . $fields['account_id']['value'] . '.eu.r2.cloudflarestorage.com';
		}

		// add necessary configuration values.
		$configuration['region'] = 'auto';
		$configuration['endpoint'] = $endpoint;
		$configuration['use_path_style_endpoint'] = true;

		// return the resulting configuration.
		return $configuration;
	}

	/**
	 * Return the public URL of a file.
	 *
	 * @param string $key The given file key.
	 * @param array<string,mixed> $fields List of fields.
	 *
	 * @return string
	 */
	public function get_public_url_of_file( string $key, array $fields ): string {
		return 'https://' . $fields['acccunt_id']['value'] . '.r2.cloudflarestorage.com/' . $fields['bucket']['value'] . '/' . $key;
	}
}
