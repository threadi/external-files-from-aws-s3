<?php
/**
 * Tests for class ExternalFilesFromAwsS3\Plugin\Init.
 *
 * @package external-files-from-aws-s3
 */

namespace ExternalFilesFromAwsS3\Tests\Unit\Plugin;

use ExternalFilesFromAwsS3\Platforms\AwsS3;
use ExternalFilesFromAwsS3\Platforms\BackblazeS3;
use ExternalFilesFromAwsS3\Platforms\CloudflareR2;
use ExternalFilesFromAwsS3\Platforms\DigitalOceanSpaces;
use ExternalFilesFromAwsS3\Tests\externalFilesFromAwsS3Tests;

/**
 * Object to test functions in class ExternalFilesFromAwsS3\Plugin\Init.
 */
class Init extends externalFilesFromAwsS3Tests {
	/**
	 * Test if the returned object is always the same.
	 *
	 * @return void
	 */
	public function test_get_instance(): void {
		$instance = \ExternalFilesFromAwsS3\Plugin\Init::get_instance();
		$this->assertInstanceOf( \ExternalFilesFromAwsS3\Plugin\Init::class, $instance );
		$this->assertSame( $instance, \ExternalFilesFromAwsS3\Plugin\Init::get_instance() );
	}

	/**
	 * Test if the hooks of this plugin are registered during the initialization.
	 *
	 * @return void
	 */
	public function test_init(): void {
		$init = \ExternalFilesFromAwsS3\Plugin\Init::get_instance();
		$this->assertIsInt( has_filter( 'efml_services_support', array( $init, 'add_service' ) ) );
		$this->assertIsInt( has_filter( 'efml_service_plugins', array( $init, 'remove_service_plugin' ) ) );
		$this->assertIsInt( has_filter( 'efml_configurations', array( $init, 'add_configurations' ) ) );
		$this->assertIsInt( has_action( 'init', array( $init, 'init_languages' ) ) );
		$this->assertIsInt( has_action( 'admin_enqueue_scripts', array( $init, 'add_styles_in_admin' ) ) );
		$this->assertIsInt( has_filter( 'update_plugins_github.com', array( \ExternalFilesFromAwsS3\Plugin\Updates::get_instance(), 'check' ) ) );
	}

	/**
	 * Test if the check for the main plugin is working.
	 *
	 * @return void
	 */
	public function test_is_parent_plugin_active(): void {
		$init = \ExternalFilesFromAwsS3\Plugin\Init::get_instance();

		// test 1: the main plugin is active (the bootstrap marks it as active).
		$this->assertTrue( $init->is_parent_plugin_active() );

		// test 2: the main plugin is not active.
		remove_filter( 'option_active_plugins', '_efmlawss3_add_main_plugin_as_active' );
		update_option( 'active_plugins', array( 'hello-dolly/hello.php' ) );
		$this->assertFalse( $init->is_parent_plugin_active() );

		// test 3: the main plugin is active as single plugin.
		update_option( 'active_plugins', array( 'hello-dolly/hello.php', 'external-files-in-media-library/external-files-in-media-library.php' ) );
		$this->assertTrue( $init->is_parent_plugin_active() );

		// restore the filter of the bootstrap.
		add_filter( 'option_active_plugins', '_efmlawss3_add_main_plugin_as_active' );
	}

	/**
	 * Test if all supported platforms are added to the list of services.
	 *
	 * @return void
	 */
	public function test_add_service(): void {
		$services = \ExternalFilesFromAwsS3\Plugin\Init::get_instance()->add_service( array( 'Example\Service' ) );
		$this->assertIsArray( $services );
		$this->assertCount( 11, $services );
		$this->assertContains( 'Example\Service', $services );
		$this->assertContains( 'ExternalFilesFromAwsS3\Platforms\AwsS3', $services );
		$this->assertContains( 'ExternalFilesFromAwsS3\Platforms\BackblazeS3', $services );
		$this->assertContains( 'ExternalFilesFromAwsS3\Platforms\CloudflareR2', $services );
		$this->assertContains( 'ExternalFilesFromAwsS3\Platforms\DigitalOceanSpaces', $services );
		$this->assertContains( 'ExternalFilesFromAwsS3\Platforms\S3Compatible', $services );
		$this->assertContains( 'ExternalFilesFromAwsS3\Platforms\HetznerObjectStorage', $services );
		$this->assertContains( 'ExternalFilesFromAwsS3\Platforms\IonosObjectStorage', $services );
		$this->assertContains( 'ExternalFilesFromAwsS3\Platforms\OvhcloudObjectStorage', $services );
		$this->assertContains( 'ExternalFilesFromAwsS3\Platforms\ScalewayObjectStorage', $services );
		$this->assertContains( 'ExternalFilesFromAwsS3\Platforms\Wasabi', $services );

		// each entry must be an existing class.
		foreach ( $services as $service ) {
			if ( 'Example\Service' === $service ) {
				continue;
			}
			$this->assertTrue( class_exists( $service ), 'Class ' . $service . ' does not exist.' );
		}
	}

	/**
	 * Test if the main plugin knows the platforms of this plugin as services.
	 *
	 * @return void
	 */
	public function test_services_in_main_plugin(): void {
		$services = \ExternalFilesInMediaLibrary\Services\Services::get_instance()->get_services_as_objects();
		$this->assertContains( AwsS3::get_instance(), $services );
		$this->assertContains( BackblazeS3::get_instance(), $services );
		$this->assertContains( CloudflareR2::get_instance(), $services );
		$this->assertContains( DigitalOceanSpaces::get_instance(), $services );
	}

	/**
	 * Test if this plugin is removed from the list of service plugins of the main plugin.
	 *
	 * @return void
	 */
	public function test_remove_service_plugin(): void {
		$plugins = array(
			'external-files-from-aws-s3' => 'aws',
			'other-plugin'               => 'other',
		);
		$result  = \ExternalFilesFromAwsS3\Plugin\Init::get_instance()->remove_service_plugin( $plugins ); // @phpstan-ignore argument.type
		$this->assertIsArray( $result );
		$this->assertArrayNotHasKey( 'external-files-from-aws-s3', $result );
		$this->assertArrayHasKey( 'other-plugin', $result );
	}

	/**
	 * Test if the configurations for each platform are added.
	 *
	 * @return void
	 */
	public function test_add_configurations(): void {
		$configurations = \ExternalFilesFromAwsS3\Plugin\Init::get_instance()->add_configurations( array( 'Example\Configuration' ) );
		$this->assertIsArray( $configurations );
		$this->assertCount( 11, $configurations );
		$this->assertContains( 'Example\Configuration', $configurations );
		$this->assertContains( 'ExternalFilesFromAwsS3\Platforms\AwsS3\Configuration', $configurations );
		$this->assertContains( 'ExternalFilesFromAwsS3\Platforms\BackblazeS3\Configuration', $configurations );
		$this->assertContains( 'ExternalFilesFromAwsS3\Platforms\CloudflareR2\Configuration', $configurations );
		$this->assertContains( 'ExternalFilesFromAwsS3\Platforms\DigitalOceanSpaces\Configuration', $configurations );
		$this->assertContains( 'ExternalFilesFromAwsS3\Platforms\S3Compatible\Configuration', $configurations );
		$this->assertContains( 'ExternalFilesFromAwsS3\Platforms\HetznerObjectStorage\Configuration', $configurations );
		$this->assertContains( 'ExternalFilesFromAwsS3\Platforms\Wasabi\Configuration', $configurations );
	}

	/**
	 * Test if the activation adds the capabilities for each platform to the default roles.
	 *
	 * @return void
	 */
	public function test_activation(): void {
		// remove the capabilities.
		foreach ( array( 'administrator', 'editor' ) as $role ) {
			foreach ( self::$platforms as $platform_class_name ) {
				get_role( $role )->remove_cap( 'efml_cap_' . $platform_class_name::get_instance()->get_name() );
			}
		}
		$this->assertFalse( get_role( 'administrator' )->has_cap( 'efml_cap_aws-s3' ) );

		// run the activation.
		\ExternalFilesFromAwsS3\Plugin\Init::get_instance()->activation();

		// check the capabilities.
		foreach ( self::$platforms as $platform_class_name ) {
			$cap = 'efml_cap_' . $platform_class_name::get_instance()->get_name();
			$this->assertTrue( get_role( 'administrator' )->has_cap( $cap ), 'Administrator has not the capability ' . $cap );
			$this->assertTrue( get_role( 'editor' )->has_cap( $cap ), 'Editor has not the capability ' . $cap );
			$this->assertFalse( get_role( 'subscriber' )->has_cap( $cap ), 'Subscriber has the capability ' . $cap );
		}
	}

	/**
	 * Test if the capabilities for platforms, which have been added with an update, are set.
	 *
	 * @return void
	 */
	public function test_add_missing_capabilities(): void {
		$init = \ExternalFilesFromAwsS3\Plugin\Init::get_instance();
		$this->assertIsInt( has_action( 'admin_init', array( $init, 'add_missing_capabilities' ) ) );

		// simulate an update: the new platform has no capability, the existing platforms have their own settings.
		delete_option( 'efmlawss3_platforms_with_capabilities' );
		delete_option( 'eml_service_s3-compatible_allowed_roles' );
		foreach ( array( 'administrator', 'editor' ) as $role ) {
			get_role( $role )->remove_cap( 'efml_cap_s3-compatible' );
		}

		// an existing platform, whose permissions have been removed by the user (no role has the capability).
		update_option( 'eml_service_backblaze-s3_allowed_roles', array() );
		foreach ( array( 'administrator', 'editor' ) as $role ) {
			get_role( $role )->remove_cap( 'efml_cap_backblaze-s3' );
		}

		// an existing platform, which is only allowed for administrators (without saved settings).
		delete_option( 'eml_service_cloudflare-r2_allowed_roles' );
		get_role( 'editor' )->remove_cap( 'efml_cap_cloudflare-r2' );
		get_role( 'administrator' )->add_cap( 'efml_cap_cloudflare-r2' );

		// run the check.
		$init->add_missing_capabilities();

		// test 1: the new platform has the capabilities for the default roles.
		$this->assertTrue( get_role( 'administrator' )->has_cap( 'efml_cap_s3-compatible' ) );
		$this->assertTrue( get_role( 'editor' )->has_cap( 'efml_cap_s3-compatible' ) );

		// test 2: the saved permissions of the existing platforms are not changed.
		$this->assertFalse( get_role( 'administrator' )->has_cap( 'efml_cap_backblaze-s3' ) );
		$this->assertTrue( get_role( 'administrator' )->has_cap( 'efml_cap_cloudflare-r2' ) );
		$this->assertFalse( get_role( 'editor' )->has_cap( 'efml_cap_cloudflare-r2' ) );

		// test 3: each platform is checked only once.
		$this->assertContains( 's3-compatible', get_option( 'efmlawss3_platforms_with_capabilities' ) );
		get_role( 'editor' )->remove_cap( 'efml_cap_s3-compatible' );
		get_role( 'administrator' )->remove_cap( 'efml_cap_s3-compatible' );
		$init->add_missing_capabilities();
		$this->assertFalse( get_role( 'administrator' )->has_cap( 'efml_cap_s3-compatible' ) );

		// cleanup.
		delete_option( 'eml_service_backblaze-s3_allowed_roles' );
		\ExternalFilesFromAwsS3\Plugin\Init::get_instance()->activation();
	}

	/**
	 * Test if the plugin is registered for the WP Consent API.
	 *
	 * @return void
	 */
	public function test_register_consent_api(): void {
		$this->assertTrue( \ExternalFilesFromAwsS3\Plugin\Init::get_instance()->register_consent_api() );
	}

	/**
	 * Test if the styles are only loaded on the supported pages.
	 *
	 * @return void
	 */
	public function test_add_styles_in_admin(): void {
		$init = \ExternalFilesFromAwsS3\Plugin\Init::get_instance();

		// test 1: not on a supported page.
		$init->add_styles_in_admin( 'index.php' );
		$this->assertFalse( wp_style_is( 'efmlawss3-admin' ) );

		// test 2: on the media library page.
		$init->add_styles_in_admin( 'upload.php' );
		$this->assertTrue( wp_style_is( 'efmlawss3-admin' ) );
		$this->assertStringEndsWith( 'admin/style.css', wp_styles()->registered['efmlawss3-admin']->src );

		// cleanup.
		wp_dequeue_style( 'efmlawss3-admin' );
		wp_deregister_style( 'efmlawss3-admin' );
	}

	/**
	 * Test the check for the required version of the main plugin.
	 *
	 * @return void
	 */
	public function test_is_parent_plugin_compatible(): void {
		$init = \ExternalFilesFromAwsS3\Plugin\Init::get_instance();

		$this->assertEquals( '6.0.0', $init->get_required_parent_plugin_version() );
		$this->assertTrue( $init->is_parent_plugin_compatible( '6.0.0' ) );
		$this->assertTrue( $init->is_parent_plugin_compatible( '6.1.2' ) );
		$this->assertFalse( $init->is_parent_plugin_compatible( '5.6.2' ) );
		$this->assertFalse( $init->is_parent_plugin_compatible( '6.0.0-beta1' ) );

		// development versions without version number are compatible.
		$this->assertTrue( $init->is_parent_plugin_compatible( '@@VersionNumber@@' ) );
	}

	/**
	 * Test the hint for an incompatible main plugin: it is shown and removed again.
	 *
	 * @return void
	 */
	public function test_incompatible_parent_plugin_hint(): void {
		$init       = \ExternalFilesFromAwsS3\Plugin\Init::get_instance();
		$transients = \ExternalFilesInMediaLibrary\Dependencies\easyTransientsForWordPress\Transients::get_instance();

		$init->show_incompatible_parent_plugin_hint();
		$transient = $transients->get_transient_by_name( 'efmlawss3_incompatible_parent_plugin' );
		$this->assertTrue( $transient->is_set() );
		$this->assertStringContainsString( '6.0.0', $transient->get_message() );

		$init->remove_incompatible_parent_plugin_hint();
		$this->assertFalse( $transients->get_transient_by_name( 'efmlawss3_incompatible_parent_plugin' )->is_set() );

		// the hint is removed on each request with a compatible main plugin.
		$this->assertIsInt( has_action( 'admin_init', array( $init, 'remove_incompatible_parent_plugin_hint' ) ) );
	}
}
