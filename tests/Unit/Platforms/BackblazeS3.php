<?php
/**
 * Tests for class ExternalFilesFromAwsS3\Platforms\BackblazeS3.
 *
 * @package external-files-from-aws-s3
 */

namespace ExternalFilesFromAwsS3\Tests\Unit\Platforms;

use Aws\Api\DateTimeResult;
use Aws\S3\S3Client;
use ExternalFilesFromAwsS3\Platforms\BackblazeS3\Export;
use ExternalFilesFromAwsS3\Tests\externalFilesFromAwsS3Tests;
use ExternalFilesInMediaLibrary\ExternalFiles\Protocols\Http;
use ExternalFilesInMediaLibrary\Services\Service;

/**
 * Object to test functions in class ExternalFilesFromAwsS3\Platforms\BackblazeS3.
 */
class BackblazeS3 extends externalFilesFromAwsS3Tests {
	/**
	 * The values for the fields we use in the tests.
	 *
	 * @var array<string,string>
	 */
	private array $field_values = array(
		'access_key' => 'backblaze-key',
		'secret'     => 'backblaze-secret',
		'bucket'     => 'my-bucket',
		'region'     => 'eu-central-003',
	);

	/**
	 * Return the object to test.
	 *
	 * @return \ExternalFilesFromAwsS3\Platforms\BackblazeS3
	 */
	private function get_platform(): \ExternalFilesFromAwsS3\Platforms\BackblazeS3 {
		return \ExternalFilesFromAwsS3\Platforms\BackblazeS3::get_instance();
	}

	/**
	 * Test if the returned object is always the same.
	 *
	 * @return void
	 */
	public function test_get_instance(): void {
		$instance = $this->get_platform();
		$this->assertInstanceOf( \ExternalFilesFromAwsS3\Platforms\BackblazeS3::class, $instance );
		$this->assertInstanceOf( Service::class, $instance );
		$this->assertSame( $instance, \ExternalFilesFromAwsS3\Platforms\BackblazeS3::get_instance() );
	}

	/**
	 * Test if the returning variables are the expected strings.
	 *
	 * @return void
	 */
	public function test_get_name_and_label(): void {
		$this->assertEquals( 'backblaze-s3', $this->get_platform()->get_name() );
		$this->assertEquals( 'Backblaze S3', $this->get_platform()->get_label() );
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
		$this->assertEquals( 'https://my-bucket.s3.eu-central-003.backblazeb2.com/', $this->get_platform()->get_url_mark() );
		$this->assertEquals( 'https://my-bucket.s3.eu-central-003.backblazeb2.com/', $this->get_platform()->get_directory() );
	}

	/**
	 * Test if the public URL of a file is generated.
	 *
	 * @return void
	 */
	public function test_get_public_url_of_file(): void {
		$fields = $this->set_platform_fields( $this->get_platform(), $this->field_values );
		$this->assertEquals( 'https://my-bucket.s3.eu-central-003.backblazeb2.com/dir/example.jpg', $this->get_platform()->get_public_url_of_file( 'dir/example.jpg', $fields ) );
	}

	/**
	 * Test if the URL mark is removed from the requested URL.
	 *
	 * @return void
	 */
	public function test_get_requested_url(): void {
		$fields = $this->set_platform_fields( $this->get_platform(), $this->field_values );
		$this->assertEquals( 'dir/example.jpg', $this->get_platform()->get_requested_url( 'https://my-bucket.s3.eu-central-003.backblazeb2.com/dir/example.jpg', $fields ) );
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
		$this->assertEquals(
			array( 'us-west-004', 'us-east-005', 'eu-central-003', 'ap-southeast-002' ),
			array_column( $fields['region']['options'], 'value' )
		);
	}

	/**
	 * Test if the fields use the global settings if this mode is enabled.
	 *
	 * @return void
	 */
	public function test_get_fields_in_global_mode(): void {
		update_option( 'eml_backblaze-s3_credentials_vault', 'global' );
		update_option( 'eml_backblaze_s3_access_key', $this->get_platform()->encrypt_value( 'global-key' ) );
		update_option( 'eml_backblaze_s3_secret_key', $this->get_platform()->encrypt_value( 'global-secret' ) );
		update_option( 'eml_backblaze_s3_bucket', 'global-bucket' );
		update_option( 'eml_backblaze_s3_region', 'us-west-004' );

		$fields = $this->get_platform()->get_fields();
		$this->assertEquals( 'global-key', $fields['access_key']['value'] );
		$this->assertEquals( 'global-secret', $fields['secret']['value'] );
		$this->assertEquals( 'global-bucket', $fields['bucket']['value'] );
		$this->assertEquals( 'us-west-004', $fields['region']['value'] );
		$this->assertTrue( $fields['access_key']['readonly'] );
		$this->assertTrue( $fields['bucket']['readonly'] );
	}

	/**
	 * Test the regions and the default region.
	 *
	 * @return void
	 */
	public function test_regions(): void {
		// test 1: the list of regions is filterable.
		$callback = static function ( array $regions ): array {
			$regions['custom-001'] = 'custom-001';
			return $regions;
		};
		add_filter( 'efmlawss3_service_backblaze_s3_regions', $callback );
		$this->assertContains( 'custom-001', array_column( $this->get_platform()->get_fields()['region']['options'], 'value' ) );
		remove_filter( 'efmlawss3_service_backblaze_s3_regions', $callback );

		// test 2: the default region for german.
		$german = static function (): string {
			return 'de';
		};
		add_filter( 'efml_current_language', $german );
		$this->assertEquals( 'eu-central-003', $this->call_method( $this->get_platform(), 'get_mapping_region' ) );
		remove_filter( 'efml_current_language', $german );

		// test 3: no default region for other languages, but filterable.
		$this->assertEquals( '', $this->call_method( $this->get_platform(), 'get_mapping_region' ) );
		$default = static function (): string {
			return 'us-east-005';
		};
		add_filter( 'efmlawss3_service_backblaze_s3_default_region', $default );
		$this->assertEquals( 'us-east-005', $this->call_method( $this->get_platform(), 'get_mapping_region' ) );
		remove_filter( 'efmlawss3_service_backblaze_s3_default_region', $default );
	}

	/**
	 * Test the user settings for this platform.
	 *
	 * @return void
	 */
	public function test_get_user_settings(): void {
		$settings = $this->get_platform()->get_user_settings();
		$this->assertArrayHasKey( 'backblaze_s3_access_key', $settings );
		$this->assertArrayHasKey( 'backblaze_s3_secret_key', $settings );
		$this->assertArrayHasKey( 'backblaze_s3_bucket', $settings );

		// the list of settings is filterable.
		$callback = static function ( array $list ): array {
			$list['custom'] = array( 'label' => 'Custom' );
			return $list;
		};
		add_filter( 'efmlawss3_service_backblaze_s3_user_settings', $callback );
		$this->assertArrayHasKey( 'custom', $this->get_platform()->get_user_settings() );
		remove_filter( 'efmlawss3_service_backblaze_s3_user_settings', $callback );
	}

	/**
	 * Test if the user settings contain the region, which is read in the user mode.
	 *
	 *
	 * @return void
	 */
	public function test_get_user_settings_contains_region(): void {
		$this->assertArrayHasKey( 'backblaze_s3_region', $this->get_platform()->get_user_settings() );
	}

	/**
	 * Test if the export object and the configuration are returned.
	 *
	 * @return void
	 */
	public function test_get_export_and_configuration_object(): void {
		$this->assertInstanceOf( Export::class, $this->get_platform()->get_export_object() );
		$this->assertEquals( 'ExternalFilesFromAwsS3\Platforms\BackblazeS3\Configuration', $this->get_platform()->get_configuration_object_name() );
	}

	/**
	 * Test the actions for files and directories.
	 *
	 * @return void
	 */
	public function test_get_actions(): void {
		$this->set_platform_fields( $this->get_platform(), $this->field_values );
		$this->assertStringContainsString( '"service": "backblaze-s3"', $this->get_platform()->get_actions()[0]['action'] );

		$actions = $this->call_method( $this->get_platform(), 'get_global_actions' );
		$labels  = array_column( $actions, 'label' );
		$this->assertContains( 'Import active directory', $labels );
		$this->assertStringContainsString( 'https://my-bucket.s3.eu-central-003.backblazeb2.com/', $actions[ array_search( 'Import active directory', $labels, true ) ]['action'] );
	}

	/**
	 * Test if the content type check and the mime type are only changed for Backblaze URLs.
	 *
	 * @return void
	 */
	public function test_allow_content_type_and_set_real_mime_type(): void {
		$url = 'https://my-bucket.s3.eu-central-003.backblazeb2.com/example.pdf';
		$this->assertFalse( $this->get_platform()->allow_content_type( true, $url ) );
		$this->assertTrue( $this->get_platform()->allow_content_type( true, 'https://example.com/example.pdf' ) );
		$this->assertEquals( 'application/pdf', $this->get_platform()->set_real_mime_type( array( 'mime-type' => 'binary/octet-stream' ), $url )['mime-type'] );
		$this->assertEquals( 'binary/octet-stream', $this->get_platform()->set_real_mime_type( array( 'mime-type' => 'binary/octet-stream' ), 'https://example.com/example.pdf' )['mime-type'] );
	}

	/**
	 * Test if the authorization header is removed for Backblaze URLs.
	 *
	 * @return void
	 */
	public function test_remove_authorization_header(): void {
		$args   = array( 'headers' => array( 'Authorization' => 'Basic abc' ) );
		$result = $this->get_platform()->remove_authorization_header( $args, new Http( 'https://my-bucket.s3.eu-central-003.backblazeb2.com/example.jpg' ) );
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
	 * Test if the S3 client is configured for Backblaze.
	 *
	 * @return void
	 */
	public function test_get_s3_client(): void {
		$this->set_platform_fields( $this->get_platform(), $this->field_values );
		$client = $this->get_platform()->get_s3_client();
		$this->assertInstanceOf( S3Client::class, $client );
		$this->assertEquals( 'eu-central-003', $client->getRegion() );
		$this->assertEquals( 'https://s3.eu-central-003.backblazeb2.com', (string) $client->getEndpoint() );
		$this->assertEquals( 'backblaze-key', $client->getCredentials()->wait()->getAccessKeyId() );
	}

	/**
	 * Test the login.
	 *
	 * @return void
	 */
	public function test_do_login(): void {
		// test 1: valid credentials.
		$s3 = $this->use_s3_mock( \ExternalFilesFromAwsS3\Platforms\BackblazeS3::class, array( 'ListObjectsV2' => array( 'Contents' => array() ) ) );
		$this->set_platform_fields( $s3, $this->field_values );
		$this->assertTrue( $s3->do_login( '/' ) );
		$this->assertEquals( 'my-bucket', $this->get_s3_commands()[0]['Bucket'] );

		// test 2: invalid credentials.
		$s3 = $this->use_s3_mock( \ExternalFilesFromAwsS3\Platforms\BackblazeS3::class, array( 'ListObjectsV2' => $this->get_s3_exception( 'ListObjectsV2', 401, 'InvalidAccessKeyId' ) ) );
		$this->set_platform_fields( $s3, $this->field_values );
		$this->assertFalse( $s3->do_login( '/' ) );
		$this->assertStringContainsString( 'Backblaze S3 returns with HTTP-Status 401', $this->get_error_messages( $s3 )[0] );
	}

	/**
	 * Test the directory listing of a bucket.
	 *
	 * @return void
	 */
	public function test_get_directory_listing(): void {
		$date = new DateTimeResult( '2024-01-02T03:04:05Z' );
		$s3   = $this->use_s3_mock(
			\ExternalFilesFromAwsS3\Platforms\BackblazeS3::class,
			array(
				'ListObjectsV2' => array(
					'Contents' => array(
						array( 'Key' => 'example.jpg', 'Size' => 1234, 'LastModified' => $date ),
						array( 'Key' => 'documents/example.pdf', 'Size' => 5678, 'LastModified' => $date ),
					),
				),
			)
		);
		$this->set_platform_fields( $s3, $this->field_values );

		$listing = $s3->get_directory_listing( '/' );
		$main    = 'https://my-bucket.s3.eu-central-003.backblazeb2.com/';
		$this->assertArrayHasKey( $main, $listing );
		$this->assertEquals( 'https://my-bucket.s3.eu-central-003.backblazeb2.com/example.jpg', $listing[ $main ]['files'][0]['file'] );
		$this->assertEquals( 'documents/example.pdf', $listing[ $main . 'documents/' ]['files'][0]['s3_key'] );
	}

	/**
	 * Test if the public availability of a file is checked via HTTP.
	 *
	 * @return void
	 */
	public function test_is_file_public_available(): void {
		$this->set_platform_fields( $this->get_platform(), $this->field_values );
		$client = $this->get_platform()->get_s3_client();

		// test 1: the file is public.
		$this->add_http_response( 'https://my-bucket.s3.eu-central-003.backblazeb2.com/example.jpg', 200 );
		$this->assertTrue( $this->get_platform()->is_file_public_available( 'example.jpg', $client ) );

		// test 2: the file is not public.
		$this->add_http_response( 'https://my-bucket.s3.eu-central-003.backblazeb2.com/example.jpg', 401 );
		$this->assertFalse( $this->get_platform()->is_file_public_available( 'example.jpg', $client ) );
		$this->assertEquals( 'HEAD', $this->get_http_requests()[1]['method'] );
	}

	/**
	 * Test the form title and description.
	 *
	 * @return void
	 */
	public function test_get_form_title_and_description(): void {
		$this->assertEquals( 'Connect to your Backblaze S3 bucket', $this->get_platform()->get_form_title() );

		update_option( 'eml_backblaze-s3_credentials_vault', 'manually' );
		$this->assertStringContainsString( 'https://www.backblaze.com/docs/', $this->get_platform()->get_form_description() );

		update_option( 'eml_backblaze-s3_credentials_vault', 'user' );
		$this->assertStringContainsString( 'profile.php#efml-backblaze-s3', $this->get_platform()->get_form_description() );
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
		$this->assertIsInt( has_filter( 'efmlawss3_service_backblaze_s3_hide_file', array( $platform, 'prevent_not_allowed_files' ) ) );
		$this->assertIsInt( has_filter( 'efmlawss3_backblaze_s3_query_params', array( $platform, 'change_file_query' ) ) );
		$this->assertIsInt( has_filter( 'efml_http_check_content_type', array( $platform, 'allow_content_type' ) ) );
		$this->assertIsInt( has_filter( 'efml_external_file_infos', array( $platform, 'set_real_mime_type' ) ) );
	}

	/**
	 * Test if the settings for this platform are registered.
	 *
	 * @return void
	 */
	public function test_init_backblaze_s3(): void {
		$settings_obj = \ExternalFilesInMediaLibrary\Plugin\Settings::get_instance()->get_settings_obj();
		update_option( 'eml_backblaze-s3_credentials_vault', 'global' );
		$this->do_login();
		$this->get_platform()->init_backblaze_s3();
		foreach ( array( 'eml_backblaze_s3_access_key', 'eml_backblaze_s3_secret_key', 'eml_backblaze_s3_bucket', 'eml_backblaze_s3_region', 'eml_backblaze_s3_import_limit' ) as $setting_name ) {
			$this->assertInstanceOf( \easySettingsForWordPress\Setting::class, $settings_obj->get_setting( $setting_name ), 'Setting ' . $setting_name . ' is missing.' );
		}
	}

	/**
	 * Test which URLs are detected as URLs of Backblaze S3.
	 *
	 * @return void
	 */
	public function test_is_platform_url(): void {
		$this->assertTrue( $this->get_platform()->is_platform_url( 'https://my-bucket.s3.eu-central-003.backblazeb2.com/example.jpg' ) );
		$this->assertFalse( $this->get_platform()->is_platform_url( 'https://example.com/example.jpg' ) );
	}

	/**
	 * Test if the fields use the user settings incl. the region if this mode is enabled.
	 *
	 * @return void
	 */
	public function test_get_fields_in_user_mode(): void {
		update_option( 'eml_backblaze-s3_credentials_vault', 'user' );
		$this->do_login();
		update_user_meta( 1, 'efml_backblaze_s3_access_key', $this->get_platform()->encrypt_value( 'user-key' ) );
		update_user_meta( 1, 'efml_backblaze_s3_secret_key', $this->get_platform()->encrypt_value( 'user-secret' ) );
		update_user_meta( 1, 'efml_backblaze_s3_bucket', $this->get_platform()->encrypt_value( 'user-bucket' ) );
		update_user_meta( 1, 'efml_backblaze_s3_region', $this->get_platform()->encrypt_value( 'us-east-005' ) );

		$fields = $this->get_platform()->get_fields();
		$this->assertEquals( 'user-key', $fields['access_key']['value'] );
		$this->assertEquals( 'user-bucket', $fields['bucket']['value'] );
		$this->assertEquals( 'us-east-005', $fields['region']['value'] );
		$this->assertEquals( 'https://user-bucket.s3.us-east-005.backblazeb2.com/', $this->get_platform()->get_url_mark() );

		// the region setting in the profile uses the regions of Backblaze.
		$setting = $this->get_platform()->get_user_settings()['backblaze_s3_region'];
		$this->assertEquals( 'select', $setting['field'] );
		$this->assertArrayHasKey( 'us-east-005', $setting['options'] );
	}
}
