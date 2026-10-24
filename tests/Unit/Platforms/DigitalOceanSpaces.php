<?php
/**
 * Tests for class ExternalFilesFromAwsS3\Platforms\DigitalOceanSpaces.
 *
 * @package external-files-from-aws-s3
 */

namespace ExternalFilesFromAwsS3\Tests\Unit\Platforms;

use Aws\Api\DateTimeResult;
use Aws\S3\S3Client;
use ExternalFilesFromAwsS3\Platforms\DigitalOceanSpaces\Export;
use ExternalFilesFromAwsS3\Tests\externalFilesFromAwsS3Tests;
use ExternalFilesInMediaLibrary\ExternalFiles\Protocols\Http;
use ExternalFilesInMediaLibrary\Services\Service;

/**
 * Object to test functions in class ExternalFilesFromAwsS3\Platforms\DigitalOceanSpaces.
 */
class DigitalOceanSpaces extends externalFilesFromAwsS3Tests {
	/**
	 * The values for the fields we use in the tests.
	 *
	 * @var array<string,string>
	 */
	private array $field_values = array(
		'access_key' => 'spaces-key',
		'secret'     => 'spaces-secret',
		'bucket'     => 'my-bucket',
		'region'     => 'fra1',
	);

	/**
	 * Return the object to test.
	 *
	 * @return \ExternalFilesFromAwsS3\Platforms\DigitalOceanSpaces
	 */
	private function get_platform(): \ExternalFilesFromAwsS3\Platforms\DigitalOceanSpaces {
		return \ExternalFilesFromAwsS3\Platforms\DigitalOceanSpaces::get_instance();
	}

	/**
	 * Test if the returned object is always the same.
	 *
	 * @return void
	 */
	public function test_get_instance(): void {
		$instance = $this->get_platform();
		$this->assertInstanceOf( \ExternalFilesFromAwsS3\Platforms\DigitalOceanSpaces::class, $instance );
		$this->assertInstanceOf( Service::class, $instance );
		$this->assertSame( $instance, \ExternalFilesFromAwsS3\Platforms\DigitalOceanSpaces::get_instance() );
	}

	/**
	 * Test if the returning variables are the expected strings.
	 *
	 * @return void
	 */
	public function test_get_name_and_label(): void {
		$this->assertEquals( 'digital-ocean-spaces', $this->get_platform()->get_name() );
		$this->assertEquals( 'DigitalOcean Spaces', $this->get_platform()->get_label() );
	}

	/**
	 * Test if the URL mark and the directory are generated from bucket and region.
	 *
	 * @return void
	 */
	public function test_get_url_mark_and_directory(): void {
		// test 1: without bucket.
		$this->assertEquals( '/', $this->get_platform()->get_directory() );

		// test 2: with bucket.
		$this->set_platform_fields( $this->get_platform(), $this->field_values );
		$this->assertEquals( 'https://my-bucket.fra1.digitaloceanspaces.com/', $this->get_platform()->get_url_mark() );
		$this->assertEquals( 'https://my-bucket.fra1.digitaloceanspaces.com/', $this->get_platform()->get_directory() );
	}

	/**
	 * Test if the public URL of a file is generated.
	 *
	 * @return void
	 */
	public function test_get_public_url_of_file(): void {
		$fields = $this->set_platform_fields( $this->get_platform(), $this->field_values );
		$this->assertEquals( 'https://my-bucket.fra1.digitaloceanspaces.com/dir/example.jpg', $this->get_platform()->get_public_url_of_file( 'dir/example.jpg', $fields ) );
	}

	/**
	 * Test if the URL mark is removed from the requested URL.
	 *
	 * @return void
	 */
	public function test_get_requested_url(): void {
		$fields = $this->set_platform_fields( $this->get_platform(), $this->field_values );
		$this->assertEquals( 'dir/example.jpg', $this->get_platform()->get_requested_url( 'https://my-bucket.fra1.digitaloceanspaces.com/dir/example.jpg', $fields ) );
	}

	/**
	 * Test the fields for the connection.
	 *
	 * @return void
	 */
	public function test_get_fields(): void {
		$fields = $this->get_platform()->get_fields();
		$this->assertEquals( array( 'access_key', 'secret', 'bucket', 'region' ), array_keys( $fields ) );
		$this->assertTrue( $fields['access_key']['credential'] );
		$this->assertTrue( $fields['secret']['credential'] );

		$regions = array_column( $fields['region']['options'], 'value' );
		$this->assertCount( 13, $regions );
		$this->assertContains( 'fra1', $regions );
		$this->assertContains( 'nyc3', $regions );
	}

	/**
	 * Test if the fields use the global settings if this mode is enabled.
	 *
	 * @return void
	 */
	public function test_get_fields_in_global_mode(): void {
		update_option( 'eml_digital-ocean-spaces_credentials_vault', 'global' );
		update_option( 'eml_digital_ocean_spaces_access_key', $this->get_platform()->encrypt_value( 'global-key' ) );
		update_option( 'eml_digital_ocean_spaces_secret_key', $this->get_platform()->encrypt_value( 'global-secret' ) );
		update_option( 'eml_digital_ocean_spaces_bucket', 'global-bucket' );
		update_option( 'eml_digital_ocean_spaces_region', 'ams3' );

		$fields = $this->get_platform()->get_fields();
		$this->assertEquals( 'global-key', $fields['access_key']['value'] );
		$this->assertEquals( 'global-secret', $fields['secret']['value'] );
		$this->assertEquals( 'global-bucket', $fields['bucket']['value'] );
		$this->assertEquals( 'ams3', $fields['region']['value'] );
		$this->assertTrue( $fields['secret']['readonly'] );
	}

	/**
	 * Test if the fields use the user settings if this mode is enabled.
	 *
	 * @return void
	 */
	public function test_get_fields_in_user_mode(): void {
		update_option( 'eml_digital-ocean-spaces_credentials_vault', 'user' );
		$this->do_login();
		update_user_meta( 1, 'efml_digital_ocean_spaces_access_key', $this->get_platform()->encrypt_value( 'user-key' ) );
		update_user_meta( 1, 'efml_digital_ocean_spaces_secret_key', $this->get_platform()->encrypt_value( 'user-secret' ) );
		update_user_meta( 1, 'efml_digital_ocean_spaces_bucket', $this->get_platform()->encrypt_value( 'user-bucket' ) );
		update_user_meta( 1, 'efml_digital_ocean_spaces_region', $this->get_platform()->encrypt_value( 'sgp1' ) );

		$fields = $this->get_platform()->get_fields();
		$this->assertEquals( 'user-key', $fields['access_key']['value'] );
		$this->assertEquals( 'user-bucket', $fields['bucket']['value'] );
		$this->assertEquals( 'sgp1', $fields['region']['value'] );
	}

	/**
	 * Test the regions and the default region.
	 *
	 * @return void
	 */
	public function test_regions(): void {
		// test 1: the list of regions is filterable.
		$callback = static function (): array {
			return array( 'custom1' => 'Custom' );
		};
		add_filter( 'efmlawss3_service_digital_ocean_spaces_regions', $callback );
		$this->assertEquals( array( 'custom1' => 'Custom' ), $this->get_platform()->get_user_settings()['digital_ocean_spaces_region']['options'] );
		remove_filter( 'efmlawss3_service_digital_ocean_spaces_regions', $callback );

		// test 2: the default region for german.
		$german = static function (): string {
			return 'de';
		};
		add_filter( 'efml_current_language', $german );
		$this->assertEquals( 'fra1', $this->get_platform()->get_user_settings()['digital_ocean_spaces_region']['default'] );
		remove_filter( 'efml_current_language', $german );

		// test 3: no default region for other languages, but filterable.
		$this->assertEquals( '', $this->get_platform()->get_user_settings()['digital_ocean_spaces_region']['default'] );
		$default = static function (): string {
			return 'ams3';
		};
		add_filter( 'efmlawss3_service_digital_ocean_spaces_default_region', $default );
		$this->assertEquals( 'ams3', $this->get_platform()->get_user_settings()['digital_ocean_spaces_region']['default'] );
		remove_filter( 'efmlawss3_service_digital_ocean_spaces_default_region', $default );
	}

	/**
	 * Test the user settings for this platform.
	 *
	 * @return void
	 */
	public function test_get_user_settings(): void {
		$settings = $this->get_platform()->get_user_settings();
		$this->assertEquals( array( 'digital_ocean_spaces_access_key', 'digital_ocean_spaces_secret_key', 'digital_ocean_spaces_bucket', 'digital_ocean_spaces_region' ), array_keys( $settings ) );

		// the list of settings is filterable.
		$callback = static function ( array $list ): array {
			$list['custom'] = array( 'label' => 'Custom' );
			return $list;
		};
		add_filter( 'efmlawss3_service_digital_ocean_spaces_user_settings', $callback );
		$this->assertArrayHasKey( 'custom', $this->get_platform()->get_user_settings() );
		remove_filter( 'efmlawss3_service_digital_ocean_spaces_user_settings', $callback );
	}

	/**
	 * Test if the export object and the configuration are returned.
	 *
	 * @return void
	 */
	public function test_get_export_and_configuration_object(): void {
		$this->assertInstanceOf( Export::class, $this->get_platform()->get_export_object() );
		$this->assertEquals( 'ExternalFilesFromAwsS3\Platforms\DigitalOceanSpaces\Configuration', $this->get_platform()->get_configuration_object_name() );
	}

	/**
	 * Test the actions for files and directories.
	 *
	 * @return void
	 */
	public function test_get_actions(): void {
		$this->set_platform_fields( $this->get_platform(), $this->field_values );
		$this->assertStringContainsString( '"service": "digital-ocean-spaces"', $this->get_platform()->get_actions()[0]['action'] );

		$actions = $this->call_method( $this->get_platform(), 'get_global_actions' );
		$labels  = array_column( $actions, 'label' );
		$this->assertStringContainsString( 'https://my-bucket.fra1.digitaloceanspaces.com/', $actions[ array_search( 'Import active directory', $labels, true ) ]['action'] );
	}

	/**
	 * Test if the content type check and the mime type are not changed for other URLs.
	 *
	 * @return void
	 */
	public function test_allow_content_type_and_set_real_mime_type_for_other_urls(): void {
		$this->assertTrue( $this->get_platform()->allow_content_type( true, 'https://example.com/example.pdf' ) );
		$this->assertEquals( 'binary/octet-stream', $this->get_platform()->set_real_mime_type( array( 'mime-type' => 'binary/octet-stream' ), 'https://example.com/example.pdf' )['mime-type'] );
	}

	/**
	 * Test if the content type check and the mime type are changed for DigitalOcean Spaces URLs.
	 *
	 *
	 * @return void
	 */
	public function test_allow_content_type_and_set_real_mime_type(): void {
		$url = 'https://my-bucket.fra1.digitaloceanspaces.com/example.pdf';
		$this->assertFalse( $this->get_platform()->allow_content_type( true, $url ) );
		$this->assertEquals( 'application/pdf', $this->get_platform()->set_real_mime_type( array( 'mime-type' => 'binary/octet-stream' ), $url )['mime-type'] );
	}

	/**
	 * Test if the authorization header is removed for DigitalOcean Spaces URLs.
	 *
	 * @return void
	 */
	public function test_remove_authorization_header(): void {
		$args   = array( 'headers' => array( 'Authorization' => 'Basic abc' ) );
		$result = $this->get_platform()->remove_authorization_header( $args, new Http( 'https://my-bucket.fra1.digitaloceanspaces.com/example.jpg' ) );
		$this->assertArrayNotHasKey( 'Authorization', $result['headers'] );
	}

	/**
	 * Test if the authorization header is kept for other URLs.
	 *
	 *
	 * @return void
	 */
	public function test_remove_authorization_header_for_other_urls(): void {
		$args   = array( 'headers' => array( 'Authorization' => 'Basic abc' ) );
		$result = $this->get_platform()->remove_authorization_header( $args, new Http( 'https://example.com/example.jpg' ) );
		$this->assertArrayHasKey( 'Authorization', $result['headers'] );
	}

	/**
	 * Test if the S3 client is configured for DigitalOcean Spaces.
	 *
	 * @return void
	 */
	public function test_get_s3_client(): void {
		$this->set_platform_fields( $this->get_platform(), $this->field_values );
		$client = $this->get_platform()->get_s3_client();
		$this->assertInstanceOf( S3Client::class, $client );
		$this->assertEquals( 'fra1', $client->getRegion() );
		$this->assertEquals( 'https://fra1.digitaloceanspaces.com', (string) $client->getEndpoint() );
		$this->assertEquals( 'spaces-key', $client->getCredentials()->wait()->getAccessKeyId() );
	}

	/**
	 * Test the login.
	 *
	 * @return void
	 */
	public function test_do_login(): void {
		// test 1: valid credentials.
		$s3 = $this->use_s3_mock( \ExternalFilesFromAwsS3\Platforms\DigitalOceanSpaces::class, array( 'ListObjectsV2' => array( 'Contents' => array() ) ) );
		$this->set_platform_fields( $s3, $this->field_values );
		$this->assertTrue( $s3->do_login( '/' ) );

		// test 2: invalid credentials.
		$s3 = $this->use_s3_mock( \ExternalFilesFromAwsS3\Platforms\DigitalOceanSpaces::class, array( 'ListObjectsV2' => $this->get_s3_exception( 'ListObjectsV2', 403 ) ) );
		$this->set_platform_fields( $s3, $this->field_values );
		$this->assertFalse( $s3->do_login( '/' ) );
		$this->assertStringContainsString( 'DigitalOcean Spaces returns with HTTP-Status 403', $this->get_error_messages( $s3 )[0] );
	}

	/**
	 * Test the directory listing of a bucket.
	 *
	 * @return void
	 */
	public function test_get_directory_listing(): void {
		$date = new DateTimeResult( '2024-01-02T03:04:05Z' );
		$s3   = $this->use_s3_mock(
			\ExternalFilesFromAwsS3\Platforms\DigitalOceanSpaces::class,
			array(
				'ListObjectsV2' => array(
					'Contents' => array(
						array( 'Key' => 'example.jpg', 'Size' => 1234, 'LastModified' => $date ),
					),
				),
			)
		);
		$this->set_platform_fields( $s3, $this->field_values );

		$listing = $s3->get_directory_listing( '/' );
		$this->assertEquals( 'https://my-bucket.fra1.digitaloceanspaces.com/example.jpg', $listing['https://my-bucket.fra1.digitaloceanspaces.com/']['files'][0]['file'] );
	}

	/**
	 * Test if the public availability of a file is checked via HTTP.
	 *
	 * @return void
	 */
	public function test_is_file_public_available(): void {
		$this->set_platform_fields( $this->get_platform(), $this->field_values );
		$client = $this->get_platform()->get_s3_client();

		$this->add_http_response( 'https://my-bucket.fra1.digitaloceanspaces.com/example.jpg', 200 );
		$this->assertTrue( $this->get_platform()->is_file_public_available( 'example.jpg', $client ) );

		$this->add_http_response( 'https://my-bucket.fra1.digitaloceanspaces.com/example.jpg', 403 );
		$this->assertFalse( $this->get_platform()->is_file_public_available( 'example.jpg', $client ) );
	}

	/**
	 * Test the form title and description.
	 *
	 * @return void
	 */
	public function test_get_form_title_and_description(): void {
		$this->assertEquals( 'Connect to your DigitalOcean Spaces bucket', $this->get_platform()->get_form_title() );

		update_option( 'eml_digital-ocean-spaces_credentials_vault', 'user' );
		$this->assertStringContainsString( 'profile.php#efml-digital-ocean-spaces', $this->get_platform()->get_form_description() );
	}

	/**
	 * Test if the description in the manual mode links to the documentation of DigitalOcean.
	 *
	 *
	 * @return void
	 */
	public function test_get_form_description_links_to_digitalocean(): void {
		update_option( 'eml_digital-ocean-spaces_credentials_vault', 'manually' );
		$this->assertStringContainsString( 'digitalocean.com', $this->get_platform()->get_form_description() );
	}

	/**
	 * Test if the hooks are registered for users with the capability for this platform.
	 *
	 * @return void
	 */
	public function test_init(): void {
		$platform = $this->get_platform();
		$this->do_login();
		$platform->init();
		$this->assertIsInt( has_filter( 'efml_protocols', array( $platform, 'add_protocol' ) ) );
		$this->assertIsInt( has_filter( 'efmlawss3_service_digitalocean_spaces_hide_file', array( $platform, 'prevent_not_allowed_files' ) ) );
		$this->assertIsInt( has_filter( 'efmlawss3_digitalocean_spaces_query_params', array( $platform, 'change_file_query' ) ) );
	}

	/**
	 * Test if the settings for this platform are registered.
	 *
	 * @return void
	 */
	public function test_init_digital_ocean_spaces(): void {
		$settings_obj = \ExternalFilesInMediaLibrary\Plugin\Settings::get_instance()->get_settings_obj();
		update_option( 'eml_digital-ocean-spaces_credentials_vault', 'global' );
		$this->do_login();
		$this->get_platform()->init_digital_ocean_spaces();
		foreach ( array( 'eml_digital_ocean_spaces_access_key', 'eml_digital_ocean_spaces_secret_key', 'eml_digital_ocean_spaces_bucket', 'eml_digital_ocean_spaces_region', 'eml_digital_ocean_spaces_import_limit' ) as $setting_name ) {
			$this->assertInstanceOf( \easySettingsForWordPress\Setting::class, $settings_obj->get_setting( $setting_name ), 'Setting ' . $setting_name . ' is missing.' );
		}
	}

	/**
	 * Test which URLs are detected as URLs of DigitalOcean Spaces.
	 *
	 * @return void
	 */
	public function test_is_platform_url(): void {
		$this->assertTrue( $this->get_platform()->is_platform_url( 'https://my-bucket.fra1.digitaloceanspaces.com/example.jpg' ) );
		$this->assertFalse( $this->get_platform()->is_platform_url( 'https://example.com/example.jpg' ) );
		$this->assertFalse( $this->get_platform()->is_platform_url( 'https://www.digitalocean.com/example.jpg' ) );
	}

	/**
	 * Test the labels of the regions.
	 *
	 * @return void
	 */
	public function test_region_labels(): void {
		foreach ( $this->get_platform()->get_fields()['region']['options'] as $option ) {
			$this->assertStringStartsWith( strtoupper( $option['value'] ) . ' ', $option['label'] );
		}
	}
}
