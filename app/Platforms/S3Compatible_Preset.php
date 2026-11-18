<?php
/**
 * File for the base object of presets for S3-compatible providers.
 *
 * @package external-files-from-aws-s3
 */

namespace ExternalFilesFromAwsS3\Platforms;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Base object for presets of S3-compatible providers.
 *
 * A preset is the S3-compatible storage with a fixed list of regions and their endpoints.
 * The URLs of the buckets are always virtual-hosted (https://bucket.endpoint/).
 *
 * Each preset must declare:
 * - the properties $name, $label, $protocol_class_name, $configuration_object_name, $export_class_name and $settings_sub_tab,
 * - the static property $instance,
 * - the method get_provider_regions().
 */
abstract class S3Compatible_Preset extends S3Compatible {
	/**
	 * List of fields, which identify the bucket of this platform (region and bucket).
	 *
	 * @var array<int,string>
	 */
	protected array $constants_target_fields = array( 'region', 'bucket' );

	/**
	 * Return the regions of this provider with their endpoints.
	 *
	 * @return array<string,array<string,string>>
	 */
	abstract protected function get_provider_regions(): array;

	/**
	 * Return the regions of this platform with their endpoints.
	 *
	 * @return array<string,array<string,string>>
	 */
	public function get_regions(): array {
		// get the regions of this provider.
		$regions = $this->get_provider_regions();

		$instance = $this;
		/**
		 * Filter the regions of a preset for an S3-compatible provider.
		 *
		 * Each region has the region as key and an array with the "label" and the "endpoint" as value.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 * @param array<string,array<string,string>> $regions List of regions.
		 * @param S3Compatible_Preset $instance The platform object.
		 */
		return apply_filters( 'efmlawss3_s3_compatible_preset_regions', $regions, $instance );
	}
}
