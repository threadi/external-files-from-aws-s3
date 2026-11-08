<?php
/**
 * File to handle the support for OVHcloud Object Storage.
 *
 * @package external-files-from-aws-s3
 */

namespace ExternalFilesFromAwsS3\Platforms;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Object to handle the support for OVHcloud Object Storage as preset of the S3-compatible storage.
 */
class OvhcloudObjectStorage extends S3Compatible_Preset {
	/**
	 * The object name.
	 *
	 * @var string
	 */
	protected string $name = 'ovhcloud-object-storage';

	/**
	 * The public label.
	 *
	 * @var string
	 */
	protected string $label = 'OVHcloud Object Storage';

	/**
	 * The class name of the protocol class.
	 *
	 * @var string
	 */
	protected string $protocol_class_name = 'ExternalFilesFromAwsS3\Platforms\OvhcloudObjectStorage\Protocol';

	/**
	 * Name of the configuration object.
	 *
	 * @var string
	 */
	protected string $configuration_object_name = 'ExternalFilesFromAwsS3\Platforms\OvhcloudObjectStorage\Configuration';

	/**
	 * The class name of the export object.
	 *
	 * @var string
	 */
	protected string $export_class_name = 'ExternalFilesFromAwsS3\Platforms\OvhcloudObjectStorage\Export';

	/**
	 * Slug of settings tab.
	 *
	 * @var string
	 */
	protected string $settings_sub_tab = 'eml_ovhcloud_object_storage';

	/**
	 * Instance of actual object.
	 *
	 * @var ?S3Compatible
	 */
	protected static ?S3Compatible $instance = null;

	/**
	 * Return the regions of this provider with their endpoints.
	 *
	 * @source https://docs.ovhcloud.com/en/guides/storage-and-backup/object-storage/s3-location
	 *
	 * @return array<string,array<string,string>>
	 */
	protected function get_provider_regions(): array {
		return array(
			'gra'              => array(
				'label'    => 'Gravelines (gra)',
				'endpoint' => 'https://s3.gra.io.cloud.ovh.net',
			),
			'rbx'              => array(
				'label'    => 'Roubaix (rbx)',
				'endpoint' => 'https://s3.rbx.io.cloud.ovh.net',
			),
			'sbg'              => array(
				'label'    => 'Strasbourg (sbg)',
				'endpoint' => 'https://s3.sbg.io.cloud.ovh.net',
			),
			'eu-west-par'      => array(
				'label'    => 'Paris (eu-west-par)',
				'endpoint' => 'https://s3.eu-west-par.io.cloud.ovh.net',
			),
			'eu-south-mil'     => array(
				'label'    => 'Milan (eu-south-mil)',
				'endpoint' => 'https://s3.eu-south-mil.io.cloud.ovh.net',
			),
			'de'               => array(
				'label'    => 'Frankfurt (de)',
				'endpoint' => 'https://s3.de.io.cloud.ovh.net',
			),
			'uk'               => array(
				'label'    => 'London (uk)',
				'endpoint' => 'https://s3.uk.io.cloud.ovh.net',
			),
			'waw'              => array(
				'label'    => 'Warsaw (waw)',
				'endpoint' => 'https://s3.waw.io.cloud.ovh.net',
			),
			'bhs'              => array(
				'label'    => 'Beauharnois (bhs)',
				'endpoint' => 'https://s3.bhs.io.cloud.ovh.net',
			),
			'ca-east-tor'      => array(
				'label'    => 'Toronto (ca-east-tor)',
				'endpoint' => 'https://s3.ca-east-tor.io.cloud.ovh.net',
			),
			'sgp'              => array(
				'label'    => 'Singapore (sgp)',
				'endpoint' => 'https://s3.sgp.io.cloud.ovh.net',
			),
			'ap-southeast-syd' => array(
				'label'    => 'Sydney (ap-southeast-syd)',
				'endpoint' => 'https://s3.ap-southeast-syd.io.cloud.ovh.net',
			),
			'ap-south-mum'     => array(
				'label'    => 'Mumbai (ap-south-mum)',
				'endpoint' => 'https://s3.ap-south-mum.io.cloud.ovh.net',
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
		if ( ! isset( $this->get_regions()['de'] ) ) {
			return parent::get_default_region();
		}

		// return the default region.
		return 'de';
	}

	/**
	 * Return the URL of the documentation of this platform.
	 *
	 * @return string
	 */
	public function get_documentation_url(): string {
		return 'https://docs.ovhcloud.com/en/guides/storage-and-backup/object-storage/s3-getting-started-with-object-storage';
	}
}
