<?php
/**
 * Tests for class ExternalFilesFromAwsS3\Platforms\CloudflareR2.
 *
 * @package external-files-from-aws-s3
 */

namespace ExternalFilesFromAwsS3\Tests\Unit\Platforms;

use Aws\Api\DateTimeResult;
use ExternalFilesFromAwsS3\Platforms\CloudflareR2\Export;
use ExternalFilesFromAwsS3\Tests\externalFilesFromAwsS3Tests;
use ExternalFilesInMediaLibrary\ExternalFiles\Protocols\Http;
use ExternalFilesInMediaLibrary\Services\Service;

/**
 * Object to test functions in class ExternalFilesFromAwsS3\Platforms\CloudflareR2.
 */
class CloudflareR2 extends externalFilesFromAwsS3Tests {
	/**
	 * The values for the fields we use in the tests.
	 *
	 * @var array<string,string|int>
	 */
	private array $field_values = array(
		'account_id'    => 'account123',
		'eu'            => 0,
		'access_key'    => 'r2-key',
		'secret'        => 'r2-secret',
		'bucket'        => 'my-bucket',
		'public_domain' => '',
	);

	/**
	 * Return the object to test.
	 *
	 * @return \ExternalFilesFromAwsS3\Platforms\CloudflareR2
	 */
	private function get_platform(): \ExternalFilesFromAwsS3\Platforms\CloudflareR2 {
		return \ExternalFilesFromAwsS3\Platforms\CloudflareR2::get_instance();
	}

	/**
	 * Test if the returned object is always the same.
	 *
	 * @return void
	 */
	public function test_get_instance(): void {
		$instance = $this->get_platform();
		$this->assertInstanceOf( \ExternalFilesFromAwsS3\Platforms\CloudflareR2::class, $instance );
		$this->assertInstanceOf( Service::class, $instance );
		$this->assertSame( $instance, \ExternalFilesFromAwsS3\Platforms\CloudflareR2::get_instance() );
	}

	/**
	 * Test if the returning variables are the expected strings.
	 *
	 * @return void
	 */
	public function test_get_name_and_label(): void {
		$this->assertEquals( 'cloudflare-r2', $this->get_platform()->get_name() );
		$this->assertEquals( 'Cloudflare R2', $this->get_platform()->get_label() );
	}

	/**
	 * Test if the URL mark and the directory are generated from the account ID.
	 *
	 * @return void
	 */
	public function test_get_url_mark_and_directory(): void {
		// test 1: without bucket.
		$this->assertEquals( '/', $this->get_platform()->get_directory() );

		// test 2: with bucket.
		$this->set_platform_fields( $this->get_platform(), $this->field_values );
		$this->assertEquals( 'https://dash.cloudflare.com/account123/', $this->get_platform()->get_url_mark() );
		$this->assertEquals( 'https://dash.cloudflare.com/account123/', $this->get_platform()->get_directory() );
	}

	/**
	 * Test if the URL mark is removed from the requested URL.
	 *
	 * @return void
	 */
	public function test_get_requested_url(): void {
		$fields = $this->set_platform_fields( $this->get_platform(), $this->field_values );
		$this->assertEquals( 'dir/example.jpg', $this->get_platform()->get_requested_url( 'https://dash.cloudflare.com/account123/dir/example.jpg', $fields ) );
	}

	/**
	 * Test the fields for the connection.
	 *
	 * @return void
	 */
	public function test_get_fields(): void {
		$fields = $this->get_platform()->get_fields();
		$this->assertEquals( array( 'account_id', 'eu', 'access_key', 'secret', 'bucket', 'public_domain' ), array_keys( $fields ) );
		$this->assertTrue( $fields['account_id']['credential'] );
		$this->assertTrue( $fields['access_key']['credential'] );
		$this->assertTrue( $fields['secret']['credential'] );
		$this->assertEquals( 'checkbox', $fields['eu']['type'] );
		$this->assertTrue( $fields['eu']['not_required'] );
		$this->assertTrue( $fields['public_domain']['not_required'] );
	}

	/**
	 * Test if the fields use the global settings if this mode is enabled.
	 *
	 * @return void
	 */
	public function test_get_fields_in_global_mode(): void {
		update_option( 'eml_cloudflare-r2_credentials_vault', 'global' );
		update_option( 'eml_cloudflare_r2_account_id', $this->get_platform()->encrypt_value( 'global-account' ) );
		update_option( 'eml_cloudflare_r2_access_key', $this->get_platform()->encrypt_value( 'global-key' ) );
		update_option( 'eml_cloudflare_r2_secret_key', $this->get_platform()->encrypt_value( 'global-secret' ) );
		update_option( 'eml_cloudflare_r2_bucket', $this->get_platform()->encrypt_value( 'global-bucket' ) );
		update_option( 'eml_cloudflare_r2_public_domain', 'cdn.example.com' );

		$fields = $this->get_platform()->get_fields();
		$this->assertEquals( 'global-account', $fields['account_id']['value'] );
		$this->assertEquals( 'global-key', $fields['access_key']['value'] );
		$this->assertEquals( 'global-secret', $fields['secret']['value'] );
		$this->assertEquals( 'global-bucket', $fields['bucket']['value'] );
		$this->assertEquals( 'cdn.example.com', $fields['public_domain']['value'] );
		$this->assertTrue( $fields['account_id']['readonly'] );
	}

	/**
	 * Test if the EU storage setting is used in the global mode.
	 *
	 *
	 * @return void
	 */
	public function test_get_fields_in_global_mode_with_eu_storage(): void {
		update_option( 'eml_cloudflare-r2_credentials_vault', 'global' );
		update_option( 'eml_cloudflare_r2_eu', 1 );

		$this->assertEquals( 1, absint( $this->get_platform()->get_fields()['eu']['value'] ) );
	}

	/**
	 * Test the user settings for this platform.
	 *
	 * @return void
	 */
	public function test_get_user_settings(): void {
		$settings = $this->get_platform()->get_user_settings();
		$this->assertEquals( array( 'cloudflare_r2_account_id', 'cloudflare_r2_eu', 'cloudflare_r2_access_key', 'cloudflare_r2_secret_key', 'cloudflare_r2_bucket', 'cloudflare_r2_public_domain' ), array_keys( $settings ) );
		$this->assertEquals( 'checkbox', $settings['cloudflare_r2_eu']['field'] );

		// the list of settings is filterable.
		$callback = static function ( array $list ): array {
			$list['custom'] = array( 'label' => 'Custom' );
			return $list;
		};
		add_filter( 'efmlawss3_service_cloudflare_r2_user_settings', $callback );
		$this->assertArrayHasKey( 'custom', $this->get_platform()->get_user_settings() );
		remove_filter( 'efmlawss3_service_cloudflare_r2_user_settings', $callback );
	}

	/**
	 * Test if the export object and the configuration are returned.
	 *
	 * @return void
	 */
	public function test_get_export_and_configuration_object(): void {
		$this->assertInstanceOf( Export::class, $this->get_platform()->get_export_object() );
		$this->assertEquals( 'ExternalFilesFromAwsS3\Platforms\CloudflareR2\Configuration', $this->get_platform()->get_configuration_object_name() );
	}

	/**
	 * Test the actions for files and directories.
	 *
	 * @return void
	 */
	public function test_get_actions(): void {
		$this->set_platform_fields( $this->get_platform(), $this->field_values );
		$this->assertStringContainsString( '"service": "cloudflare-r2"', $this->get_platform()->get_actions()[0]['action'] );

		$actions = $this->call_method( $this->get_platform(), 'get_global_actions' );
		$labels  = array_column( $actions, 'label' );
		$this->assertStringContainsString( 'https://dash.cloudflare.com/account123/', $actions[ array_search( 'Import active directory', $labels, true ) ]['action'] );
	}

	/**
	 * Test the public URL of a file without custom domain.
	 *
	 * @return void
	 */
	public function test_get_public_url_of_file_without_domain(): void {
		// test 1: standard storage.
		$fields = $this->set_platform_fields( $this->get_platform(), $this->field_values );
		$this->assertEquals( 'https://dash.cloudflare.com/account123/r2/buckets/my-bucket/objects/dir/example.jpg', $this->get_platform()->get_public_url_of_file( 'dir/example.jpg', $fields ) );

		// test 2: EU storage.
		$fields = $this->set_platform_fields( $this->get_platform(), array( 'eu' => 1 ) );
		$this->assertEquals( 'https://dash.cloudflare.com/account123/r2/eu/buckets/my-bucket/objects/dir/example.jpg', $this->get_platform()->get_public_url_of_file( 'dir/example.jpg', $fields ) );

		// no HTTP request is necessary.
		$this->assertEmpty( $this->get_http_requests() );
	}

	/**
	 * Test the public URL of a file with custom domain.
	 *
	 * @return void
	 */
	public function test_get_public_url_of_file_with_domain(): void {
		// the domain is used with or without scheme and trailing slash, and the key is encoded per path segment.
		foreach ( array( 'cdn.example.com', 'https://cdn.example.com/', ' http://cdn.example.com ' ) as $domain ) {
			$fields = $this->set_platform_fields( $this->get_platform(), array_merge( $this->field_values, array( 'public_domain' => $domain ) ) );
			$this->add_http_response( 'https://cdn.example.com/dir/my%20file.jpg', 200 );
			$this->assertEquals( 'https://cdn.example.com/dir/my%20file.jpg', $this->get_platform()->get_public_url_of_file( 'dir/my file.jpg', $fields ), 'Wrong URL for domain ' . $domain );
		}
	}

	/**
	 * Test if the dashboard link is used if the file is not available via the custom domain.
	 *
	 * @return void
	 */
	public function test_get_public_url_of_file_with_not_working_domain(): void {
		$fields = $this->set_platform_fields( $this->get_platform(), array_merge( $this->field_values, array( 'public_domain' => 'cdn.example.com' ) ) );
		$this->add_http_response( 'https://cdn.example.com/example.jpg', 404 );

		$this->assertEquals( 'https://dash.cloudflare.com/account123/r2/buckets/my-bucket/objects/example.jpg', $this->get_platform()->get_public_url_of_file( 'example.jpg', $fields ) );

		// the problem must be logged.
		$log = $this->get_log_entries( 'https://cdn.example.com/example.jpg' );
		$this->assertNotEmpty( $log );
		$this->assertEquals( 'error', $log[0]['state'] );
	}

	/**
	 * Test the public availability of a file.
	 *
	 * @return void
	 */
	public function test_is_file_public_available(): void {
		// test 1: without custom domain no file is public available.
		$this->set_platform_fields( $this->get_platform(), $this->field_values );
		$client = $this->get_platform()->get_s3_client();
		$this->assertFalse( $this->get_platform()->is_file_public_available( 'example.jpg', $client ) );
		$this->assertEmpty( $this->get_http_requests() );

		// test 2: with custom domain, which serves the file.
		$this->set_platform_fields( $this->get_platform(), array( 'public_domain' => 'cdn.example.com' ) );
		$this->add_http_response( 'https://cdn.example.com/example.jpg', 200 );
		$this->assertTrue( $this->get_platform()->is_file_public_available( 'example.jpg', $client ) );

		// test 3: with custom domain, which does not serve the file.
		$this->add_http_response( 'https://cdn.example.com/example.jpg', 403 );
		$this->assertFalse( $this->get_platform()->is_file_public_available( 'example.jpg', $client ) );
	}

	/**
	 * Test if the S3 client is configured for Cloudflare R2.
	 *
	 * @return void
	 */
	public function test_get_s3_client(): void {
		$s3 = $this->use_s3_mock( \ExternalFilesFromAwsS3\Platforms\CloudflareR2::class, array() );

		// test 1: standard storage.
		$this->set_platform_fields( $s3, $this->field_values );
		$s3->get_s3_client();
		$configuration = $this->get_s3_configurations()[0];
		$this->assertEquals( 'auto', $configuration['region'] );
		$this->assertEquals( 'https://account123.r2.cloudflarestorage.com', $configuration['endpoint'] );
		$this->assertTrue( $configuration['use_path_style_endpoint'] );
		$this->assertEquals( 'r2-key', $configuration['credentials']['key'] );
		$this->assertEquals( 'r2-secret', $configuration['credentials']['secret'] );

		// test 2: EU storage.
		$this->set_platform_fields( $s3, array( 'eu' => 1 ) );
		$s3->get_s3_client();
		$this->assertEquals( 'https://account123.eu.r2.cloudflarestorage.com', $this->get_s3_configurations()[1]['endpoint'] );
	}

	/**
	 * Test if the content type check and the mime type are only changed for Cloudflare URLs.
	 *
	 * @return void
	 */
	public function test_allow_content_type_and_set_real_mime_type(): void {
		$url = 'https://dash.cloudflare.com/account123/r2/buckets/my-bucket/objects/example.pdf';
		$this->assertFalse( $this->get_platform()->allow_content_type( true, $url ) );
		$this->assertTrue( $this->get_platform()->allow_content_type( true, 'https://example.com/example.pdf' ) );
		$this->assertEquals( 'application/pdf', $this->get_platform()->set_real_mime_type( array( 'mime-type' => 'binary/octet-stream' ), $url )['mime-type'] );
		$this->assertEquals( 'binary/octet-stream', $this->get_platform()->set_real_mime_type( array( 'mime-type' => 'binary/octet-stream' ), 'https://example.com/example.pdf' )['mime-type'] );
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
	 * Test the login.
	 *
	 * @return void
	 */
	public function test_do_login(): void {
		// test 1: valid credentials.
		$s3 = $this->use_s3_mock( \ExternalFilesFromAwsS3\Platforms\CloudflareR2::class, array( 'ListObjectsV2' => array( 'Contents' => array() ) ) );
		$this->set_platform_fields( $s3, $this->field_values );
		$this->assertTrue( $s3->do_login( '/' ) );

		// test 2: invalid credentials.
		$s3 = $this->use_s3_mock( \ExternalFilesFromAwsS3\Platforms\CloudflareR2::class, array( 'ListObjectsV2' => $this->get_s3_exception( 'ListObjectsV2', 403 ) ) );
		$this->set_platform_fields( $s3, $this->field_values );
		$this->assertFalse( $s3->do_login( '/' ) );
		$this->assertStringContainsString( 'Cloudflare R2 returns with HTTP-Status 403', $this->get_error_messages( $s3 )[0] );
	}

	/**
	 * Test the directory listing of a bucket.
	 *
	 * @return void
	 */
	public function test_get_directory_listing(): void {
		$date = new DateTimeResult( '2024-01-02T03:04:05Z' );
		$s3   = $this->use_s3_mock(
			\ExternalFilesFromAwsS3\Platforms\CloudflareR2::class,
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
		$this->assertEquals( 'https://dash.cloudflare.com/account123/r2/buckets/my-bucket/objects/example.jpg', $listing['https://dash.cloudflare.com/account123/']['files'][0]['file'] );
	}

	/**
	 * Test the form title and description.
	 *
	 * @return void
	 */
	public function test_get_form_title_and_description(): void {
		$this->assertEquals( 'Connect to your Cloudflare R2', $this->get_platform()->get_form_title() );

		update_option( 'eml_cloudflare-r2_credentials_vault', 'manually' );
		$this->assertStringContainsString( 'https://developers.cloudflare.com/r2/', $this->get_platform()->get_form_description() );

		update_option( 'eml_cloudflare-r2_credentials_vault', 'user' );
		$this->assertStringContainsString( 'profile.php#efml-cloudflare-r2', $this->get_platform()->get_form_description() );
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
		$this->assertIsInt( has_filter( 'efmlawss3_service_cloudflare_r2_hide_file', array( $platform, 'prevent_not_allowed_files' ) ) );
		$this->assertIsInt( has_filter( 'efmlawss3_cloudflare_r2_query_params', array( $platform, 'change_file_query' ) ) );
	}

	/**
	 * Test if the settings for this platform are registered.
	 *
	 * @return void
	 */
	public function test_init_cloudflare_r2(): void {
		$settings_obj = \ExternalFilesInMediaLibrary\Plugin\Settings::get_instance()->get_settings_obj();
		update_option( 'eml_cloudflare-r2_credentials_vault', 'global' );
		$this->do_login();
		$this->get_platform()->init_cloudflare_r2();
		foreach ( array( 'eml_cloudflare_r2_account_id', 'eml_cloudflare_r2_eu', 'eml_cloudflare_r2_access_key', 'eml_cloudflare_r2_secret_key', 'eml_cloudflare_r2_bucket', 'eml_cloudflare_r2_public_domain', 'eml_cloudflare_r2_import_limit' ) as $setting_name ) {
			$this->assertInstanceOf( \easySettingsForWordPress\Setting::class, $settings_obj->get_setting( $setting_name ), 'Setting ' . $setting_name . ' is missing.' );
		}
	}

	/**
	 * Test which URLs are detected as URLs of Cloudflare R2.
	 *
	 * @return void
	 */
	public function test_is_platform_url(): void {
		$this->set_platform_fields( $this->get_platform(), array_merge( $this->field_values, array( 'public_domain' => 'https://CDN.example.com/' ) ) );
		$this->assertTrue( $this->get_platform()->is_platform_url( 'https://dash.cloudflare.com/account123/r2/buckets/my-bucket/objects/example.jpg' ) );
		$this->assertTrue( $this->get_platform()->is_platform_url( 'https://account123.r2.cloudflarestorage.com/my-bucket/example.jpg' ) );
		$this->assertTrue( $this->get_platform()->is_platform_url( 'https://cdn.example.com/example.jpg' ) );
		$this->assertFalse( $this->get_platform()->is_platform_url( 'https://example.com/example.jpg' ) );
		$this->assertFalse( $this->get_platform()->is_platform_url( 'https://example.com/cloudflare.com/example.jpg' ) );
		$this->assertFalse( $this->get_platform()->is_platform_url( 'https://other.example.com/cdn.example.com/example.jpg' ) );
	}

	/**
	 * Test if the authorization header is removed for URLs of Cloudflare R2.
	 *
	 * @return void
	 */
	public function test_remove_authorization_header(): void {
		$this->set_platform_fields( $this->get_platform(), array_merge( $this->field_values, array( 'public_domain' => 'cdn.example.com' ) ) );
		$args = array( 'headers' => array( 'Authorization' => 'Basic abc' ) );
		foreach ( array( 'https://dash.cloudflare.com/account123/r2/buckets/my-bucket/objects/example.jpg', 'https://cdn.example.com/example.jpg' ) as $url ) {
			$result = $this->get_platform()->remove_authorization_header( $args, new Http( $url ) );
			$this->assertArrayNotHasKey( 'Authorization', $result['headers'], 'Authorization not removed for ' . $url );
		}
	}

	/**
	 * Test if the EU storage of the global settings is used for the S3 client.
	 *
	 * @return void
	 */
	public function test_get_s3_client_in_global_mode_with_eu_storage(): void {
		update_option( 'eml_cloudflare-r2_credentials_vault', 'global' );
		update_option( 'eml_cloudflare_r2_account_id', $this->get_platform()->encrypt_value( 'account123' ) );
		update_option( 'eml_cloudflare_r2_eu', 1 );

		$s3 = $this->use_s3_mock( \ExternalFilesFromAwsS3\Platforms\CloudflareR2::class, array() );
		$s3->get_s3_client();
		$this->assertEquals( 'https://account123.eu.r2.cloudflarestorage.com', $this->get_s3_configurations()[0]['endpoint'] );

		// without the setting the standard storage is used.
		delete_option( 'eml_cloudflare_r2_eu' );
		$s3->set_fields( array() );
		$this->assertEquals( 0, $s3->get_fields()['eu']['value'] );
	}

	/**
	 * Test if the EU storage of the user settings is used.
	 *
	 * @return void
	 */
	public function test_get_fields_in_user_mode_with_eu_storage(): void {
		update_option( 'eml_cloudflare-r2_credentials_vault', 'user' );
		$this->do_login();
		update_user_meta( 1, 'efml_cloudflare_r2_eu', $this->get_platform()->encrypt_value( '1' ) );
		update_user_meta( 1, 'efml_cloudflare_r2_account_id', $this->get_platform()->encrypt_value( 'user-account' ) );

		$fields = $this->get_platform()->get_fields();
		$this->assertEquals( 1, absint( $fields['eu']['value'] ) );
		$this->assertEquals( 'user-account', $fields['account_id']['value'] );
	}
}
