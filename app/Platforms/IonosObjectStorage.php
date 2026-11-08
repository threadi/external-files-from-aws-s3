<?php
/**
 * File to handle the support for IONOS Object Storage.
 *
 * @package external-files-from-aws-s3
 */

namespace ExternalFilesFromAwsS3\Platforms;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Object to handle the support for IONOS Object Storage as preset of the S3-compatible storage.
 */
class IonosObjectStorage extends S3Compatible_Preset {
	/**
	 * The object name.
	 *
	 * @var string
	 */
	protected string $name = 'ionos-object-storage';

	/**
	 * The public label.
	 *
	 * @var string
	 */
	protected string $label = 'IONOS Object Storage';

	/**
	 * The class name of the protocol class.
	 *
	 * @var string
	 */
	protected string $protocol_class_name = 'ExternalFilesFromAwsS3\Platforms\IonosObjectStorage\Protocol';

	/**
	 * Name of the configuration object.
	 *
	 * @var string
	 */
	protected string $configuration_object_name = 'ExternalFilesFromAwsS3\Platforms\IonosObjectStorage\Configuration';

	/**
	 * The class name of the export object.
	 *
	 * @var string
	 */
	protected string $export_class_name = 'ExternalFilesFromAwsS3\Platforms\IonosObjectStorage\Export';

	/**
	 * Slug of settings tab.
	 *
	 * @var string
	 */
	protected string $settings_sub_tab = 'eml_ionos_object_storage';

	/**
	 * Instance of actual object.
	 *
	 * @var ?S3Compatible
	 */
	protected static ?S3Compatible $instance = null;

	/**
	 * Return the regions of this provider with their endpoints.
	 *
	 * @source https://docs.ionos.com/cloud/storage-and-backup/ionos-object-storage/endpoints
	 *
	 * @return array<string,array<string,string>>
	 */
	protected function get_provider_regions(): array {
		return array(
			'eu-central-4' => array(
				'label'    => 'Frankfurt (eu-central-4, contract-owned buckets)',
				'endpoint' => 'https://s3.eu-central-4.ionoscloud.com',
			),
			'eu-central-3' => array(
				'label'    => 'Berlin (eu-central-3, contract-owned buckets)',
				'endpoint' => 'https://s3.eu-central-3.ionoscloud.com',
			),
			'us-central-1' => array(
				'label'    => 'Lenexa, USA (us-central-1, contract-owned buckets)',
				'endpoint' => 'https://s3.us-central-1.ionoscloud.com',
			),
			'de'           => array(
				'label'    => 'Frankfurt (de, user-owned buckets)',
				'endpoint' => 'https://s3.eu-central-1.ionoscloud.com',
			),
			'eu-central-2' => array(
				'label'    => 'Berlin (eu-central-2, user-owned buckets)',
				'endpoint' => 'https://s3.eu-central-2.ionoscloud.com',
			),
			'eu-south-2'   => array(
				'label'    => 'Logroño, Spain (eu-south-2, user-owned buckets)',
				'endpoint' => 'https://s3.eu-south-2.ionoscloud.com',
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
		if ( ! isset( $this->get_regions()['eu-central-4'] ) ) {
			return parent::get_default_region();
		}

		// return the default region.
		return 'eu-central-4';
	}

	/**
	 * Return the URL of the documentation of this platform.
	 *
	 * @return string
	 */
	public function get_documentation_url(): string {
		return 'https://docs.ionos.com/cloud/storage-and-backup/ionos-object-storage';
	}
}
