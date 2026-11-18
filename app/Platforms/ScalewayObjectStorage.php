<?php
/**
 * File to handle the support for Scaleway Object Storage.
 *
 * @package external-files-from-aws-s3
 */

namespace ExternalFilesFromAwsS3\Platforms;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Object to handle the support for Scaleway Object Storage as preset of the S3-compatible storage.
 */
class ScalewayObjectStorage extends S3Compatible_Preset {
	/**
	 * The object name.
	 *
	 * @var string
	 */
	protected string $name = 'scaleway-object-storage';

	/**
	 * The public label.
	 *
	 * @var string
	 */
	protected string $label = 'Scaleway Object Storage';

	/**
	 * The class name of the protocol class.
	 *
	 * @var string
	 */
	protected string $protocol_class_name = 'ExternalFilesFromAwsS3\Platforms\ScalewayObjectStorage\Protocol';

	/**
	 * Name of the configuration object.
	 *
	 * @var string
	 */
	protected string $configuration_object_name = 'ExternalFilesFromAwsS3\Platforms\ScalewayObjectStorage\Configuration';

	/**
	 * The class name of the export object.
	 *
	 * @var string
	 */
	protected string $export_class_name = 'ExternalFilesFromAwsS3\Platforms\ScalewayObjectStorage\Export';

	/**
	 * Slug of settings tab.
	 *
	 * @var string
	 */
	protected string $settings_sub_tab = 'eml_scaleway_object_storage';

	/**
	 * Instance of actual object.
	 *
	 * @var ?S3Compatible
	 */
	protected static ?S3Compatible $instance = null;

	/**
	 * Return the regions of this provider with their endpoints.
	 *
	 * @source https://www.scaleway.com/en/docs/object-storage/concepts/
	 *
	 * @return array<string,array<string,string>>
	 */
	protected function get_provider_regions(): array {
		return array(
			'fr-par' => array(
				'label'    => 'Paris (fr-par)',
				'endpoint' => 'https://s3.fr-par.scw.cloud',
			),
			'nl-ams' => array(
				'label'    => 'Amsterdam (nl-ams)',
				'endpoint' => 'https://s3.nl-ams.scw.cloud',
			),
			'pl-waw' => array(
				'label'    => 'Warsaw (pl-waw)',
				'endpoint' => 'https://s3.pl-waw.scw.cloud',
			),
			'it-mil' => array(
				'label'    => 'Milan (it-mil)',
				'endpoint' => 'https://s3.it-mil.scw.cloud',
			),
		);
	}

	/**
	 * Return the default region.
	 *
	 * @return string
	 */
	public function get_default_region(): string {
		// bail if the default region is not available.
		if ( ! isset( $this->get_regions()['fr-par'] ) ) {
			return parent::get_default_region();
		}

		// return the default region.
		return 'fr-par';
	}

	/**
	 * Return the URL of the documentation of this platform.
	 *
	 * @return string
	 */
	public function get_documentation_url(): string {
		return 'https://www.scaleway.com/en/docs/object-storage/';
	}

	/**
	 * Return the URL of the web interface of Scaleway (the buckets in the Scaleway console).
	 *
	 * @since 2.0.0 Available since 2.0.0.
	 *
	 * @return string
	 */
	public function get_platform_url(): string {
		return 'https://console.scaleway.com/object-storage/buckets';
	}

	/**
	 * Return the help for the setup of this platform.
	 *
	 * @return array{intro?: string, steps?: array<int,string>, hints?: array<int,string>, code?: array{label: string, content: string, hint?: string}, links?: array<int,array{label: string, url: string}>}
	 */
	public function get_setup_guide(): array {
		return array(
			'intro' => __( 'Create an API key in the Scaleway console (IAM → API keys → Generate API key), preferably for an IAM application with the permission set "ObjectStorageFullAccess" for your project.', 'external-files-from-aws-s3' ),
			'steps' => array(
				__( 'Choose the project of your bucket as preferred project for Object Storage.', 'external-files-from-aws-s3' ),
				__( 'Copy the access key and the secret key: the secret key is only shown once.', 'external-files-from-aws-s3' ),
				__( 'Choose the region of your bucket, e.g. <code>fr-par</code>.', 'external-files-from-aws-s3' ),
			),
			'hints' => array(
				__( 'Without the right preferred project, your bucket is not found.', 'external-files-from-aws-s3' ),
			),
			'links' => array(
				array(
					'label' => __( 'Create API keys', 'external-files-from-aws-s3' ),
					'url'   => 'https://www.scaleway.com/en/docs/iam/how-to/create-api-keys/',
				),
				array(
					'label' => __( 'Using API keys with Object Storage', 'external-files-from-aws-s3' ),
					'url'   => 'https://www.scaleway.com/en/docs/iam/api-cli/using-api-key-object-storage/',
				),
			),
		);
	}
}
