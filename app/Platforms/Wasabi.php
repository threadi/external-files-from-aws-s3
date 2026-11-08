<?php
/**
 * File to handle the support for Wasabi.
 *
 * @package external-files-from-aws-s3
 */

namespace ExternalFilesFromAwsS3\Platforms;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Object to handle the support for Wasabi as preset of the S3-compatible storage.
 */
class Wasabi extends S3Compatible_Preset {
	/**
	 * The object name.
	 *
	 * @var string
	 */
	protected string $name = 'wasabi';

	/**
	 * The public label.
	 *
	 * @var string
	 */
	protected string $label = 'Wasabi';

	/**
	 * The class name of the protocol class.
	 *
	 * @var string
	 */
	protected string $protocol_class_name = 'ExternalFilesFromAwsS3\Platforms\Wasabi\Protocol';

	/**
	 * Name of the configuration object.
	 *
	 * @var string
	 */
	protected string $configuration_object_name = 'ExternalFilesFromAwsS3\Platforms\Wasabi\Configuration';

	/**
	 * The class name of the export object.
	 *
	 * @var string
	 */
	protected string $export_class_name = 'ExternalFilesFromAwsS3\Platforms\Wasabi\Export';

	/**
	 * Slug of settings tab.
	 *
	 * @var string
	 */
	protected string $settings_sub_tab = 'eml_wasabi';

	/**
	 * Instance of actual object.
	 *
	 * @var ?S3Compatible
	 */
	protected static ?S3Compatible $instance = null;

	/**
	 * Return the regions of this provider with their endpoints.
	 *
	 * @source https://docs.wasabi.com/docs/service-urls-for-wasabis-storage-regions
	 *
	 * @return array<string,array<string,string>>
	 */
	protected function get_provider_regions(): array {
		return array(
			'us-east-1'      => array(
				'label'    => 'US East 1, N. Virginia (us-east-1)',
				'endpoint' => 'https://s3.us-east-1.wasabisys.com',
			),
			'us-east-2'      => array(
				'label'    => 'US East 2, N. Virginia (us-east-2)',
				'endpoint' => 'https://s3.us-east-2.wasabisys.com',
			),
			'us-central-1'   => array(
				'label'    => 'US Central 1, Texas (us-central-1)',
				'endpoint' => 'https://s3.us-central-1.wasabisys.com',
			),
			'us-west-1'      => array(
				'label'    => 'US West 1, Oregon (us-west-1)',
				'endpoint' => 'https://s3.us-west-1.wasabisys.com',
			),
			'us-west-2'      => array(
				'label'    => 'US West 2, San Jose (us-west-2)',
				'endpoint' => 'https://s3.us-west-2.wasabisys.com',
			),
			'ca-central-1'   => array(
				'label'    => 'CA Central 1, Toronto (ca-central-1)',
				'endpoint' => 'https://s3.ca-central-1.wasabisys.com',
			),
			'eu-central-1'   => array(
				'label'    => 'EU Central 1, Amsterdam (eu-central-1)',
				'endpoint' => 'https://s3.eu-central-1.wasabisys.com',
			),
			'eu-central-2'   => array(
				'label'    => 'EU Central 2, Frankfurt (eu-central-2)',
				'endpoint' => 'https://s3.eu-central-2.wasabisys.com',
			),
			'eu-west-1'      => array(
				'label'    => 'EU West 1, United Kingdom (eu-west-1)',
				'endpoint' => 'https://s3.eu-west-1.wasabisys.com',
			),
			'eu-west-2'      => array(
				'label'    => 'EU West 2, Paris (eu-west-2)',
				'endpoint' => 'https://s3.eu-west-2.wasabisys.com',
			),
			'eu-west-3'      => array(
				'label'    => 'EU West 3, United Kingdom (eu-west-3)',
				'endpoint' => 'https://s3.eu-west-3.wasabisys.com',
			),
			'eu-south-1'     => array(
				'label'    => 'EU South 1, Milan (eu-south-1)',
				'endpoint' => 'https://s3.eu-south-1.wasabisys.com',
			),
			'ap-northeast-1' => array(
				'label'    => 'AP Northeast 1, Tokyo (ap-northeast-1)',
				'endpoint' => 'https://s3.ap-northeast-1.wasabisys.com',
			),
			'ap-northeast-2' => array(
				'label'    => 'AP Northeast 2, Osaka (ap-northeast-2)',
				'endpoint' => 'https://s3.ap-northeast-2.wasabisys.com',
			),
			'ap-southeast-1' => array(
				'label'    => 'AP Southeast 1, Singapore (ap-southeast-1)',
				'endpoint' => 'https://s3.ap-southeast-1.wasabisys.com',
			),
			'ap-southeast-2' => array(
				'label'    => 'AP Southeast 2, Sydney (ap-southeast-2)',
				'endpoint' => 'https://s3.ap-southeast-2.wasabisys.com',
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
		if ( ! isset( $this->get_regions()['eu-central-2'] ) ) {
			return parent::get_default_region();
		}

		// return the default region.
		return 'eu-central-2';
	}

	/**
	 * Return the URL of the documentation of this platform.
	 *
	 * @return string
	 */
	public function get_documentation_url(): string {
		return 'https://docs.wasabi.com/docs/service-urls-for-wasabis-storage-regions';
	}
}
