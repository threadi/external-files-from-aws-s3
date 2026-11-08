<?php
/**
 * Tests for the presets of S3-compatible providers.
 *
 * @package external-files-from-aws-s3
 */

namespace ExternalFilesFromAwsS3\Tests\Unit\Platforms;

use ExternalFilesFromAwsS3\Platforms\HetznerObjectStorage;
use ExternalFilesFromAwsS3\Platforms\IonosObjectStorage;
use ExternalFilesFromAwsS3\Platforms\OvhcloudObjectStorage;
use ExternalFilesFromAwsS3\Platforms\S3Compatible;
use ExternalFilesFromAwsS3\Platforms\S3Compatible_Preset;
use ExternalFilesFromAwsS3\Platforms\ScalewayObjectStorage;
use ExternalFilesFromAwsS3\Platforms\Wasabi;
use ExternalFilesFromAwsS3\Tests\externalFilesFromAwsS3Tests;
use ExternalFilesInMediaLibrary\Services\Service;

/**
 * Object to test the presets of S3-compatible providers.
 */
class Presets extends externalFilesFromAwsS3Tests {
	/**
	 * Return the presets for the tests.
	 *
	 * Each entry contains:
	 * - the class name of the preset.
	 * - the name and the label.
	 * - a region and the expected URL of the bucket "my-bucket" in this region.
	 *
	 * @return array<string,array<int,string>>
	 */
	public function presets_provider(): array {
		return array(
			'Hetzner Object Storage'  => array( HetznerObjectStorage::class, 'hetzner-object-storage', 'Hetzner Object Storage', 'nbg1', 'https://my-bucket.nbg1.your-objectstorage.com/' ),
			'IONOS Object Storage'    => array( IonosObjectStorage::class, 'ionos-object-storage', 'IONOS Object Storage', 'de', 'https://my-bucket.s3.eu-central-1.ionoscloud.com/' ),
			'OVHcloud Object Storage' => array( OvhcloudObjectStorage::class, 'ovhcloud-object-storage', 'OVHcloud Object Storage', 'gra', 'https://my-bucket.s3.gra.io.cloud.ovh.net/' ),
			'Scaleway Object Storage' => array( ScalewayObjectStorage::class, 'scaleway-object-storage', 'Scaleway Object Storage', 'nl-ams', 'https://my-bucket.s3.nl-ams.scw.cloud/' ),
			'Wasabi'                  => array( Wasabi::class, 'wasabi', 'Wasabi', 'eu-central-2', 'https://my-bucket.s3.eu-central-2.wasabisys.com/' ),
		);
	}

	/**
	 * Return the values for the fields.
	 *
	 * @param string $region The region.
	 *
	 * @return array<string,string>
	 */
	private function get_values( string $region ): array {
		return array(
			'region'     => $region,
			'access_key' => 'preset-key',
			'secret'     => 'preset-secret',
			'bucket'     => 'my-bucket',
		);
	}

	/**
	 * Test the singleton, the name and the label.
	 *
	 * @dataProvider presets_provider
	 *
	 * @param class-string<S3Compatible_Preset> $class_name The class name of the preset.
	 * @param string                            $name       The name.
	 * @param string                            $label      The label.
	 *
	 * @return void
	 */
	public function test_get_instance_name_and_label( string $class_name, string $name, string $label ): void {
		$instance = $class_name::get_instance();
		$this->assertInstanceOf( S3Compatible_Preset::class, $instance );
		$this->assertInstanceOf( Service::class, $instance );
		$this->assertSame( $class_name, get_class( $instance ) );
		$this->assertSame( $instance, $class_name::get_instance() );
		$this->assertNotSame( $instance, S3Compatible::get_instance() );
		$this->assertEquals( $name, $instance->get_name() );
		$this->assertEquals( $label, $instance->get_label() );
		$this->assertEquals( 'eml_' . str_replace( '-', '_', $name ), $this->call_method( $instance, 'get_settings_subtab_slug' ) );
	}

	/**
	 * Test the regions and their endpoints.
	 *
	 * @dataProvider presets_provider
	 *
	 * @param class-string<S3Compatible_Preset> $class_name The class name of the preset.
	 *
	 * @return void
	 */
	public function test_get_regions( string $class_name ): void {
		$instance = $class_name::get_instance();
		$regions  = $instance->get_regions();
		$this->assertNotEmpty( $regions );
		$this->assertTrue( $instance->has_fixed_regions() );
		foreach ( $regions as $region => $settings ) {
			$this->assertNotEmpty( $settings['label'], 'Missing label for ' . $region );
			$this->assertStringStartsWith( 'https://', $settings['endpoint'], 'Wrong endpoint for ' . $region );
			$this->assertStringNotContainsString( '/', substr( $settings['endpoint'], 8 ), 'Endpoint for ' . $region . ' must not have a path.' );
		}

		// the default region exists.
		$this->assertArrayHasKey( $instance->get_default_region(), $regions );

		// the documentation is linked.
		$this->assertStringStartsWith( 'https://', $instance->get_documentation_url() );
	}

	/**
	 * Test if the regions are filterable.
	 *
	 * @return void
	 */
	public function test_regions_filter(): void {
		$callback = static function ( array $regions, S3Compatible_Preset $instance ): array {
			if ( 'hetzner-object-storage' === $instance->get_name() ) {
				$regions['xyz1'] = array(
					'label'    => 'New location',
					'endpoint' => 'https://xyz1.your-objectstorage.com',
				);
			}
			return $regions;
		};
		add_filter( 'efmlawss3_s3_compatible_preset_regions', $callback, 10, 2 );
		$this->assertArrayHasKey( 'xyz1', HetznerObjectStorage::get_instance()->get_regions() );
		$this->assertArrayNotHasKey( 'xyz1', Wasabi::get_instance()->get_regions() );
		$fields = $this->set_platform_fields( HetznerObjectStorage::get_instance(), $this->get_values( 'xyz1' ) );
		$this->assertEquals( 'https://my-bucket.xyz1.your-objectstorage.com/', HetznerObjectStorage::get_instance()->get_bucket_url( $fields ) );
		remove_filter( 'efmlawss3_s3_compatible_preset_regions', $callback );
	}

	/**
	 * Test the fields: no endpoint and no setting for path-style URLs, the region is a select.
	 *
	 * @dataProvider presets_provider
	 *
	 * @param class-string<S3Compatible_Preset> $class_name The class name of the preset.
	 *
	 * @return void
	 */
	public function test_get_fields( string $class_name ): void {
		$fields = $class_name::get_instance()->get_fields();
		$this->assertEquals( array( 'region', 'access_key', 'secret', 'bucket', 'public_url', 'checksum_when_required' ), array_keys( $fields ) );
		$this->assertEquals( 'select', $fields['region']['type'] );
		$this->assertNotEmpty( $fields['region']['options'] );
		$this->assertEquals( $class_name::get_instance()->get_default_region(), $fields['region']['value'] );
	}

	/**
	 * Test the URL of the bucket and the configuration of the client.
	 *
	 * @dataProvider presets_provider
	 *
	 * @param class-string<S3Compatible_Preset> $class_name The class name of the preset.
	 * @param string                            $name       The name.
	 * @param string                            $label      The label.
	 * @param string                            $region     The region.
	 * @param string                            $bucket_url The expected URL of the bucket.
	 *
	 * @return void
	 */
	public function test_bucket_url_and_client( string $class_name, string $name, string $label, string $region, string $bucket_url ): void {
		$instance = $class_name::get_instance();
		$fields   = $this->set_platform_fields( $instance, $this->get_values( $region ) );

		// the URLs.
		$this->assertEquals( $bucket_url, $instance->get_url_mark() );
		$this->assertEquals( $bucket_url, $instance->get_directory() );
		$this->assertEquals( $bucket_url . 'dir/example.jpg', $instance->get_public_url_of_file( 'dir/example.jpg', $fields ) );
		$this->assertTrue( $instance->is_platform_url( $bucket_url . 'dir/example.jpg' ) );
		$this->assertFalse( $instance->is_path_style( $fields ) );

		// the configuration of the client.
		$configuration = $instance->get_client_configuration( $fields );
		$this->assertEquals( $instance->get_regions()[ $region ]['endpoint'], $configuration['endpoint'] );
		$this->assertEquals( $region, $configuration['region'] );
		$this->assertFalse( $configuration['use_path_style_endpoint'] );

		// an unknown region results in no URL.
		$fields = $this->set_platform_fields( $instance, $this->get_values( 'unknown' ) );
		$this->assertEquals( '', $instance->get_bucket_url( $fields ) );
		$this->assertEquals( '/', $instance->get_directory() );
	}

	/**
	 * Test the objects for the protocol, the export and the configuration.
	 *
	 * @dataProvider presets_provider
	 *
	 * @param class-string<S3Compatible_Preset> $class_name The class name of the preset.
	 * @param string                            $name       The name.
	 * @param string                            $label      The label.
	 *
	 * @return void
	 */
	public function test_objects( string $class_name, string $name, string $label ): void {
		$instance = $class_name::get_instance();

		// the export object is a singleton of its own and uses this preset.
		$export = $instance->get_export_object();
		$this->assertInstanceOf( $class_name . '\Export', $export );
		$this->assertNotSame( $export, S3Compatible::get_instance()->get_export_object() );
		$this->assertSame( $instance, $this->call_method( $export, 'get_directory_listing_object' ) );

		// the protocol uses this preset.
		$protocol_class_name = $class_name . '\Protocol';
		$protocol            = new $protocol_class_name( 'https://example.com/example.jpg' );
		$this->assertEquals( $name, $protocol->get_name() );
		$this->assertEquals( $label, $protocol->get_title() );
		$this->assertSame( $instance, $this->call_method( $protocol, 'get_directory_listing_object' ) );

		// the configuration uses this preset.
		$configuration_class_name = $instance->get_configuration_object_name();
		$configuration            = new $configuration_class_name();
		$this->assertEquals( 'Use only ' . $label, $configuration->get_title() );
	}

	/**
	 * Test the user settings of the presets.
	 *
	 * @dataProvider presets_provider
	 *
	 * @param class-string<S3Compatible_Preset> $class_name The class name of the preset.
	 * @param string                            $name       The name.
	 *
	 * @return void
	 */
	public function test_get_user_settings( string $class_name, string $name ): void {
		$settings = $class_name::get_instance()->get_user_settings();
		$this->assertArrayHasKey( $name . '_region', $settings );
		$this->assertArrayNotHasKey( $name . '_endpoint', $settings );
		$this->assertArrayNotHasKey( $name . '_path_style', $settings );
		$this->assertEquals( 'select', $settings[ $name . '_region' ]['field'] );
		$this->assertArrayHasKey( $class_name::get_instance()->get_default_region(), $settings[ $name . '_region' ]['options'] );
	}

	/**
	 * Test the fields in the global mode.
	 *
	 * @return void
	 */
	public function test_get_fields_in_global_mode(): void {
		update_option( 'eml_hetzner-object-storage_credentials_vault', 'global' );
		update_option( 'eml_hetzner_object_storage_region', 'hel1' );
		update_option( 'eml_hetzner_object_storage_access_key', HetznerObjectStorage::get_instance()->encrypt_value( 'global-key' ) );
		update_option( 'eml_hetzner_object_storage_secret', HetznerObjectStorage::get_instance()->encrypt_value( 'global-secret' ) );
		update_option( 'eml_hetzner_object_storage_bucket', 'global-bucket' );

		$this->assertEquals( 'https://global-bucket.hel1.your-objectstorage.com/', HetznerObjectStorage::get_instance()->get_url_mark() );
		$this->assertEquals( 'global-key', HetznerObjectStorage::get_instance()->get_fields()['access_key']['value'] );

		// the settings of the generic storage are not affected.
		$this->assertEquals( '/', S3Compatible::get_instance()->get_directory() );
	}
}
