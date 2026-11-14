<?php
/**
 * File to handle the support for Hetzner Object Storage.
 *
 * @package external-files-from-aws-s3
 */

namespace ExternalFilesFromAwsS3\Platforms;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Object to handle the support for Hetzner Object Storage as preset of the S3-compatible storage.
 */
class HetznerObjectStorage extends S3Compatible_Preset {
	/**
	 * The object name.
	 *
	 * @var string
	 */
	protected string $name = 'hetzner-object-storage';

	/**
	 * The public label.
	 *
	 * @var string
	 */
	protected string $label = 'Hetzner Object Storage';

	/**
	 * The class name of the protocol class.
	 *
	 * @var string
	 */
	protected string $protocol_class_name = 'ExternalFilesFromAwsS3\Platforms\HetznerObjectStorage\Protocol';

	/**
	 * Name of the configuration object.
	 *
	 * @var string
	 */
	protected string $configuration_object_name = 'ExternalFilesFromAwsS3\Platforms\HetznerObjectStorage\Configuration';

	/**
	 * The class name of the export object.
	 *
	 * @var string
	 */
	protected string $export_class_name = 'ExternalFilesFromAwsS3\Platforms\HetznerObjectStorage\Export';

	/**
	 * Slug of settings tab.
	 *
	 * @var string
	 */
	protected string $settings_sub_tab = 'eml_hetzner_object_storage';

	/**
	 * Instance of actual object.
	 *
	 * @var ?S3Compatible
	 */
	protected static ?S3Compatible $instance = null;

	/**
	 * Return the regions of this provider with their endpoints.
	 *
	 * @source https://docs.hetzner.com/storage/object-storage/overview
	 *
	 * @return array<string,array<string,string>>
	 */
	protected function get_provider_regions(): array {
		return array(
			'fsn1' => array(
				'label'    => 'Falkenstein (fsn1)',
				'endpoint' => 'https://fsn1.your-objectstorage.com',
			),
			'nbg1' => array(
				'label'    => 'Nuremberg (nbg1)',
				'endpoint' => 'https://nbg1.your-objectstorage.com',
			),
			'hel1' => array(
				'label'    => 'Helsinki (hel1)',
				'endpoint' => 'https://hel1.your-objectstorage.com',
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
		if ( ! isset( $this->get_regions()['fsn1'] ) ) {
			return parent::get_default_region();
		}

		// return the default region.
		return 'fsn1';
	}

	/**
	 * Return the URL of the documentation of this platform.
	 *
	 * @return string
	 */
	public function get_documentation_url(): string {
		return 'https://docs.hetzner.com/storage/object-storage/';
	}

	/**
	 * Return the URL of the web interface of Hetzner (the Hetzner Console).
	 *
	 * @since 2.0.0 Available since 2.0.0.
	 *
	 * @return string
	 */
	public function get_platform_url(): string {
		return 'https://console.hetzner.com/';
	}

	/**
	 * Return the help for the setup of this platform.
	 *
	 * @return array{intro?: string, steps?: array<int,string>, hints?: array<int,string>, code?: array{label: string, content: string, hint?: string}, links?: array<int,array{label: string, url: string}>}
	 */
	public function get_setup_guide(): array {
		return array(
			'intro' => __( 'Create S3 credentials in the Hetzner Console: open your project, then Security → S3 credentials → Generate credentials.', 'external-files-from-aws-s3' ),
			'steps' => array(
				__( 'Copy the access key and the secret key: the secret key is only shown once.', 'external-files-from-aws-s3' ),
				__( 'Choose the location of your bucket as region, e.g. <code>fsn1</code>.', 'external-files-from-aws-s3' ),
			),
			'hints' => array(
				__( 'S3 credentials are valid for all buckets of the project. Use an own project for this website, if you would like to separate them.', 'external-files-from-aws-s3' ),
			),
			'links' => array(
				array(
					'label' => __( 'Generating S3 keys', 'external-files-from-aws-s3' ),
					'url'   => 'https://docs.hetzner.com/storage/object-storage/getting-started/generating-s3-keys',
				),
			),
		);
	}
}
