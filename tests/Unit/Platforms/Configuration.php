<?php
/**
 * Tests for the configuration objects of each platform, e.g. ExternalFilesFromAwsS3\Platforms\AwsS3\Configuration.
 *
 * @package external-files-from-aws-s3
 */

namespace ExternalFilesFromAwsS3\Tests\Unit\Platforms;

use ExternalFilesFromAwsS3\Platform_Base;
use ExternalFilesFromAwsS3\Tests\externalFilesFromAwsS3Tests;
use ExternalFilesInMediaLibrary\Plugin\Configuration_Base;
use ExternalFilesInMediaLibrary\Services\Services;

/**
 * Object to test the configuration objects of each platform.
 */
class Configuration extends externalFilesFromAwsS3Tests {
	/**
	 * Return the configuration objects with their platform and expected values.
	 *
	 * @return array<string,array<int,string>>
	 */
	public function configurations_provider(): array {
		return array(
			'AWS S3'              => array( \ExternalFilesFromAwsS3\Platforms\AwsS3::class, 'aws_s3', 'Use only AWS S3' ),
			'Backblaze S3'        => array( \ExternalFilesFromAwsS3\Platforms\BackblazeS3::class, 'backblaze-s3', 'Use only Backblaze S3' ),
			'Cloudflare R2'       => array( \ExternalFilesFromAwsS3\Platforms\CloudflareR2::class, 'cloudflare_r2', 'Use only Cloudflare R2' ),
			'DigitalOcean Spaces' => array( \ExternalFilesFromAwsS3\Platforms\DigitalOceanSpaces::class, 'digitalocean_spaces', 'Use only DigitalOcean Spaces' ),
		);
	}

	/**
	 * Return the configuration object of the given platform.
	 *
	 * @param class-string<Platform_Base> $platform_class_name The class name of the platform.
	 *
	 * @return Configuration_Base
	 */
	private function get_configuration( string $platform_class_name ): Configuration_Base {
		$class_name = $platform_class_name . '\Configuration';
		return new $class_name();
	}

	/**
	 * Test the name, title and hints of the configuration.
	 *
	 * @dataProvider configurations_provider
	 *
	 * @param class-string<Platform_Base> $platform_class_name The class name of the platform.
	 * @param string                      $name                The expected name.
	 * @param string                      $title               The expected title.
	 *
	 * @return void
	 */
	public function test_configuration( string $platform_class_name, string $name, string $title ): void {
		$configuration = $this->get_configuration( $platform_class_name );
		$this->assertEquals( $name, $configuration->get_name() );
		$this->assertEquals( $title, $configuration->get_title() );

		$hints = $configuration->get_dialog_hints();
		$this->assertIsArray( $hints );
		$this->assertCount( 1, $hints );
		$this->assertStringContainsString( $platform_class_name::get_instance()->get_label(), $hints[0] );
	}

	/**
	 * Test if the configuration allows only the platform and disables all other services.
	 *
	 * @dataProvider configurations_provider
	 *
	 * @param class-string<Platform_Base> $platform_class_name The class name of the platform.
	 *
	 * @return void
	 */
	public function test_run( string $platform_class_name ): void {
		$platform = $platform_class_name::get_instance();
		update_option( 'eml_disable_plugin_hints', 0 );

		$this->get_configuration( $platform_class_name )->run();

		// the platform is allowed for its default roles.
		$this->assertEquals( $platform->get_default_roles(), get_option( 'eml_service_' . $platform->get_name() . '_allowed_roles' ) );

		// all other services are disabled.
		$services = Services::get_instance()->get_services_as_objects();
		$this->assertGreaterThan( 4, count( $services ) );
		foreach ( $services as $service_obj ) {
			// bail for services without name, like the configuration itself does it.
			if ( ! method_exists( $service_obj, 'get_name' ) ) {
				continue;
			}
			if ( $service_obj->get_name() === $platform->get_name() ) {
				continue;
			}
			$this->assertEquals( array(), get_option( 'eml_service_' . $service_obj->get_name() . '_allowed_roles' ), 'Service ' . $service_obj->get_name() . ' is not disabled.' );
		}

		// the hints for other plugins are disabled.
		$this->assertEquals( 1, absint( get_option( 'eml_disable_plugin_hints' ) ) );
	}
}
