<?php
/**
 * Tests for class ExternalFilesFromAwsS3\Platforms\AwsS3.
 *
 * @package external-files-from-aws-s3
 */

namespace ExternalFilesFromAwsS3\Tests\Unit\Platforms;

use Aws\Api\DateTimeResult;
use Aws\S3\S3Client;
use ExternalFilesFromAwsS3\Platforms\AwsS3\Export;
use ExternalFilesFromAwsS3\Tests\externalFilesFromAwsS3Tests;
use ExternalFilesInMediaLibrary\ExternalFiles\Protocols\Http;
use ExternalFilesInMediaLibrary\Services\Service;

/**
 * Object to test functions in class ExternalFilesFromAwsS3\Platforms\AwsS3.
 */
class AwsS3 extends externalFilesFromAwsS3Tests {
	/**
	 * The values for the fields we use in the tests.
	 *
	 * @var array<string,string>
	 */
	private array $field_values = array(
		'access_key' => 'AKIAEXAMPLE',
		'secret'     => 'secret-example',
		'bucket'     => 'my-bucket',
		'region'     => 'eu-central-1',
	);

	/**
	 * Cleanup after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		delete_transient( 'efml_aws_s3_regions' );
		parent::tear_down();
	}

	/**
	 * Return the object to test.
	 *
	 * @return \ExternalFilesFromAwsS3\Platforms\AwsS3
	 */
	private function get_platform(): \ExternalFilesFromAwsS3\Platforms\AwsS3 {
		return \ExternalFilesFromAwsS3\Platforms\AwsS3::get_instance();
	}

	/**
	 * Test if the returned object is always the same.
	 *
	 * @return void
	 */
	public function test_get_instance(): void {
		$instance = $this->get_platform();
		$this->assertInstanceOf( \ExternalFilesFromAwsS3\Platforms\AwsS3::class, $instance );
		$this->assertInstanceOf( Service::class, $instance );
		$this->assertSame( $instance, \ExternalFilesFromAwsS3\Platforms\AwsS3::get_instance() );
	}

	/**
	 * Test if the returning variables are the expected strings.
	 *
	 * @return void
	 */
	public function test_get_name_and_label(): void {
		$this->assertEquals( 'aws-s3', $this->get_platform()->get_name() );
		$this->assertEquals( 'AWS S3', $this->get_platform()->get_label() );
		$this->assertEquals( 'efml_cap_aws-s3', $this->get_platform()->get_permission_name() );
	}

	/**
	 * Test if the URL mark is returned with and without bucket.
	 *
	 * @return void
	 */
	public function test_get_url_mark(): void {
		$this->assertEquals( 'https://console.aws.amazon.com/s3/buckets/', $this->get_platform()->get_url_mark( '' ) );
		$this->assertEquals( 'https://console.aws.amazon.com/s3/buckets/my-bucket/', $this->get_platform()->get_url_mark( 'my-bucket' ) );
	}

	/**
	 * Test if the directory depends on the bucket.
	 *
	 * @return void
	 */
	public function test_get_directory(): void {
		// test 1: without bucket.
		$this->assertEquals( '/', $this->get_platform()->get_directory() );

		// test 2: with bucket.
		$this->set_platform_fields( $this->get_platform(), $this->field_values );
		$this->assertEquals( 'https://console.aws.amazon.com/s3/buckets/my-bucket/', $this->get_platform()->get_directory() );
	}

	/**
	 * Test if the bucket name is returned from the fields.
	 *
	 * @return void
	 */
	public function test_get_bucket_name(): void {
		// test 1: without bucket.
		$this->assertEquals( '', $this->get_platform()->get_bucket_name() );

		// test 2: with bucket.
		$this->set_platform_fields( $this->get_platform(), $this->field_values );
		$this->assertEquals( 'my-bucket', $this->get_platform()->get_bucket_name() );

		// test 3: with fields, which do not contain a bucket.
		$this->get_platform()->set_fields( array( 'region' => array( 'value' => 'eu-central-1' ) ) );
		$this->assertEquals( '', $this->get_platform()->get_bucket_name() );
	}

	/**
	 * Test if the public URL of a file is generated.
	 *
	 * @return void
	 */
	public function test_get_public_url_of_file(): void {
		$fields = $this->set_platform_fields( $this->get_platform(), $this->field_values );
		$this->assertEquals( 'https://my-bucket.s3.eu-central-1.amazonaws.com/dir/example.jpg', $this->get_platform()->get_public_url_of_file( 'dir/example.jpg', $fields ) );
	}

	/**
	 * Test if the URL mark is removed from the requested URL.
	 *
	 * @return void
	 */
	public function test_get_requested_url(): void {
		$fields = $this->set_platform_fields( $this->get_platform(), $this->field_values );
		$this->assertEquals( 'dir/example.jpg', $this->get_platform()->get_requested_url( 'https://console.aws.amazon.com/s3/buckets/my-bucket/dir/example.jpg', $fields ) );
		$this->assertEquals( '', $this->get_platform()->get_requested_url( 'https://console.aws.amazon.com/s3/buckets/my-bucket/', $fields ) );
	}

	/**
	 * Test the fields for the connection.
	 *
	 * @return void
	 */
	public function test_get_fields(): void {
		$fields = $this->get_platform()->get_fields();
		$this->assertIsArray( $fields );
		$this->assertEquals( array( 'access_key', 'secret', 'bucket', 'region' ), array_keys( $fields ) );

		// the credentials must be marked as credentials.
		$this->assertTrue( $fields['access_key']['credential'] );
		$this->assertTrue( $fields['secret']['credential'] );
		$this->assertEquals( 'password', $fields['secret']['type'] );

		// without values the fields are editable.
		$this->assertFalse( $fields['access_key']['readonly'] );
		$this->assertFalse( $fields['secret']['readonly'] );
		$this->assertFalse( $fields['bucket']['readonly'] );

		// the regions are prepared for React.
		$this->assertEquals( 'select', $fields['region']['type'] );
		$this->assertNotEmpty( $fields['region']['options'] );
		$this->assertContains(
			'eu-central-1',
			array_column( $fields['region']['options'], 'value' )
		);
		foreach ( $fields['region']['options'] as $option ) {
			$this->assertArrayHasKey( 'value', $option );
			$this->assertArrayHasKey( 'label', $option );
		}
	}

	/**
	 * Test if the fields use the global settings if this mode is enabled.
	 *
	 * @return void
	 */
	public function test_get_fields_in_global_mode(): void {
		update_option( 'eml_aws-s3_credentials_vault', 'global' );
		update_option( 'eml_s3_access_key', $this->get_platform()->encrypt_value( 'AKIAGLOBAL' ) );
		update_option( 'eml_s3_secret_key', $this->get_platform()->encrypt_value( 'global-secret' ) );
		update_option( 'eml_s3_bucket', 'global-bucket' );
		update_option( 'eml_s3_region', 'us-east-1' );

		$fields = $this->get_platform()->get_fields();
		$this->assertEquals( 'AKIAGLOBAL', $fields['access_key']['value'] );
		$this->assertEquals( 'global-secret', $fields['secret']['value'] );
		$this->assertEquals( 'global-bucket', $fields['bucket']['value'] );
		$this->assertEquals( 'us-east-1', $fields['region']['value'] );
		$this->assertTrue( $fields['access_key']['readonly'] );
		$this->assertTrue( $fields['secret']['readonly'] );
		$this->assertTrue( $fields['bucket']['readonly'] );

		// check the resulting values for the connection.
		$this->assertEquals( 'global-bucket', $this->get_platform()->get_bucket_name() );
		$this->assertEquals( 'us-east-1', $this->get_platform()->get_region() );
	}

	/**
	 * Test if the fields use the user settings if this mode is enabled.
	 *
	 * @return void
	 */
	public function test_get_fields_in_user_mode(): void {
		update_option( 'eml_aws-s3_credentials_vault', 'user' );
		$this->do_login();
		update_user_meta( 1, 'efml_s3_access_key', $this->get_platform()->encrypt_value( 'AKIAUSER' ) );
		update_user_meta( 1, 'efml_s3_secret_key', $this->get_platform()->encrypt_value( 'user-secret' ) );
		update_user_meta( 1, 'efml_s3_bucket', $this->get_platform()->encrypt_value( 'user-bucket' ) );
		update_user_meta( 1, 'efml_s3_region', $this->get_platform()->encrypt_value( 'eu-west-1' ) );

		$fields = $this->get_platform()->get_fields();
		$this->assertEquals( 'AKIAUSER', $fields['access_key']['value'] );
		$this->assertEquals( 'user-secret', $fields['secret']['value'] );
		$this->assertEquals( 'user-bucket', $fields['bucket']['value'] );
		$this->assertEquals( 'eu-west-1', $fields['region']['value'] );
		$this->assertEquals( 'eu-west-1', $this->get_platform()->get_region() );
	}

	/**
	 * Test if the region is returned from the fields or from the settings.
	 *
	 * @return void
	 */
	public function test_get_region(): void {
		// test 1: from fields.
		$this->set_platform_fields( $this->get_platform(), $this->field_values );
		$this->assertEquals( 'eu-central-1', $this->get_platform()->get_region() );

		// test 2: from global setting in manual mode, if the field is empty.
		update_option( 'eml_aws-s3_credentials_vault', 'manually' );
		update_option( 'eml_s3_region', 'ap-northeast-1' );
		$this->set_platform_fields( $this->get_platform(), array( 'region' => '' ) );
		$this->assertEquals( 'ap-northeast-1', $this->get_platform()->get_region() );

		// test 3: fallback to the default in manual mode without setting.
		delete_option( 'eml_s3_region' );
		$this->assertEquals( 'eu-central-1', $this->get_platform()->get_region() );

		// test 4: nothing without mode.
		delete_option( 'eml_aws-s3_credentials_vault' );
		$this->assertEquals( '', $this->get_platform()->get_region() );
	}

	/**
	 * Test if the regions are loaded from the AWS SDK, cached and filterable.
	 *
	 * @return void
	 */
	public function test_regions(): void {
		// test 1: the regions are loaded from the SDK and cached.
		delete_transient( 'efml_aws_s3_regions' );
		$regions = $this->get_platform()->get_user_settings()['s3_region']['options'];
		$this->assertIsArray( $regions );
		$this->assertArrayHasKey( 'eu-central-1', $regions );
		$this->assertArrayHasKey( 'us-east-1', $regions );
		$this->assertEquals( $regions, get_transient( 'efml_aws_s3_regions' ) );

		// test 2: the cached list is used.
		set_transient( 'efml_aws_s3_regions', array( 'cached-region' => 'Cached region' ) );
		$this->assertEquals( array( 'cached-region' => 'Cached region' ), $this->get_platform()->get_user_settings()['s3_region']['options'] );

		// test 3: the list is filterable before caching.
		delete_transient( 'efml_aws_s3_regions' );
		$callback = static function ( array $list ): array {
			return array( 'filtered-region' => 'Filtered region' );
		};
		add_filter( 'efmlawss3_service_s3_regions', $callback );
		$this->assertEquals( array( 'filtered-region' => 'Filtered region' ), $this->get_platform()->get_user_settings()['s3_region']['options'] );
		remove_filter( 'efmlawss3_service_s3_regions', $callback );
	}

	/**
	 * Test the user settings for this platform.
	 *
	 * @return void
	 */
	public function test_get_user_settings(): void {
		$settings = $this->get_platform()->get_user_settings();
		$this->assertIsArray( $settings );
		$this->assertEquals( array( 's3_access_key', 's3_secret_key', 's3_bucket', 's3_region' ), array_keys( $settings ) );
		$this->assertEquals( 'password', $settings['s3_secret_key']['field'] );
		$this->assertEquals( 'select', $settings['s3_region']['field'] );

		// the list of settings is filterable.
		$callback = static function ( array $list ): array {
			$list['custom'] = array( 'label' => 'Custom' );
			return $list;
		};
		add_filter( 'efmlawss3_service_aws_s3_user_settings', $callback );
		$this->assertArrayHasKey( 'custom', $this->get_platform()->get_user_settings() );
		remove_filter( 'efmlawss3_service_aws_s3_user_settings', $callback );
	}

	/**
	 * Test if the default region depends on the language of the website.
	 *
	 * @return void
	 */
	public function test_default_region_by_language(): void {
		$languages = array(
			'en' => 'aws-global',
			'de' => 'eu-central-1',
			'es' => 'eu-south-2',
			'ja' => 'ap-northeast-1',
			'he' => 'il-central-1',
		);
		foreach ( $languages as $language => $region ) {
			$callback = static function () use ( $language ): string {
				return $language;
			};
			add_filter( 'efml_current_language', $callback );
			$this->assertEquals( $region, $this->get_platform()->get_user_settings()['s3_region']['default'], 'Wrong region for language ' . $language );
			remove_filter( 'efml_current_language', $callback );
		}

		// the default region for other languages is filterable.
		$callback = static function ( string $default_region, string $language ): string {
			return 'custom-' . $language;
		};
		add_filter( 'efmlawss3_service_s3_default_region', $callback, 10, 2 );
		$this->assertEquals( 'custom-en', $this->get_platform()->get_user_settings()['s3_region']['default'] );
		remove_filter( 'efmlawss3_service_s3_default_region', $callback );
	}

	/**
	 * Test if the export object is returned.
	 *
	 * @return void
	 */
	public function test_get_export_object(): void {
		$this->assertInstanceOf( Export::class, $this->get_platform()->get_export_object() );
	}

	/**
	 * Test if the name of the configuration object is returned.
	 *
	 * @return void
	 */
	public function test_get_configuration_object_name(): void {
		$name = $this->get_platform()->get_configuration_object_name();
		$this->assertEquals( 'ExternalFilesFromAwsS3\Platforms\AwsS3\Configuration', $name );
		$this->assertTrue( class_exists( $name ) );
	}

	/**
	 * Test the actions for files.
	 *
	 * @return void
	 */
	public function test_get_actions(): void {
		$actions = $this->get_platform()->get_actions();
		$this->assertIsArray( $actions );
		$this->assertCount( 1, $actions );
		$this->assertEquals( 'Import', $actions[0]['label'] );
		$this->assertStringContainsString( '"service": "aws-s3"', $actions[0]['action'] );
		$this->assertStringContainsString( 'image/jpeg', $actions[0]['show'] );
	}

	/**
	 * Test the global actions for the directory listing.
	 *
	 * @return void
	 */
	public function test_get_global_actions(): void {
		$this->set_platform_fields( $this->get_platform(), $this->field_values );
		$actions = $this->call_method( $this->get_platform(), 'get_global_actions' );
		$this->assertIsArray( $actions );

		$labels = array_column( $actions, 'label' );
		$this->assertContains( 'Go to S3 Bucket', $labels );
		$this->assertContains( 'Import active directory', $labels );
		$this->assertContains( 'Save active directory as your external source', $labels );

		// the import of the actual directory must use the URL of the bucket.
		$import_action = $actions[ array_search( 'Import active directory', $labels, true ) ];
		$this->assertStringContainsString( 'https://console.aws.amazon.com/s3/buckets/my-bucket/', $import_action['action'] );
	}

	/**
	 * Test if the content type check is only disabled for AWS S3 URLs.
	 *
	 * @return void
	 */
	public function test_allow_content_type(): void {
		$this->assertFalse( $this->get_platform()->allow_content_type( true, 'https://my-bucket.s3.eu-central-1.amazonaws.com/example.jpg' ) );
		$this->assertTrue( $this->get_platform()->allow_content_type( true, 'https://example.com/example.jpg' ) );
		$this->assertFalse( $this->get_platform()->allow_content_type( false, 'https://example.com/example.jpg' ) );
	}

	/**
	 * Test if the mime type is set by the file extension for AWS S3 URLs.
	 *
	 * @return void
	 */
	public function test_set_real_mime_type(): void {
		// test 1: an AWS S3 URL.
		$results = $this->get_platform()->set_real_mime_type( array( 'mime-type' => 'binary/octet-stream' ), 'https://my-bucket.s3.eu-central-1.amazonaws.com/example.pdf' );
		$this->assertEquals( 'application/pdf', $results['mime-type'] );

		// test 2: other URL.
		$results = $this->get_platform()->set_real_mime_type( array( 'mime-type' => 'binary/octet-stream' ), 'https://example.com/example.pdf' );
		$this->assertEquals( 'binary/octet-stream', $results['mime-type'] );
	}

	/**
	 * Test if the authorization header is only removed for AWS S3 URLs.
	 *
	 * @return void
	 */
	public function test_remove_authorization_header(): void {
		$args = array(
			'headers' => array(
				'Authorization' => 'Basic abc',
				'Accept'        => '*/*',
			),
		);

		// test 1: an AWS S3 URL.
		$result = $this->get_platform()->remove_authorization_header( $args, new Http( 'https://my-bucket.s3.eu-central-1.amazonaws.com/example.jpg' ) );
		$this->assertArrayNotHasKey( 'Authorization', $result['headers'] );
		$this->assertArrayHasKey( 'Accept', $result['headers'] );

		// test 2: other URL.
		$result = $this->get_platform()->remove_authorization_header( $args, new Http( 'https://example.com/example.jpg' ) );
		$this->assertArrayHasKey( 'Authorization', $result['headers'] );
	}

	/**
	 * Test if not supported files are hidden in the directory listing, if this is enabled.
	 *
	 * @return void
	 */
	public function test_prevent_not_allowed_files(): void {
		$jpg = array( 'Key' => 'dir/example.jpg' );
		$exe = array( 'Key' => 'dir/example.exe' );

		// test 1: setting is disabled.
		update_option( 'eml_directory_listing_hide_not_supported_file_types', 0 );
		$this->assertFalse( $this->get_platform()->prevent_not_allowed_files( false, $exe, '/' ) );

		// test 2: setting is enabled.
		update_option( 'eml_directory_listing_hide_not_supported_file_types', 1 );
		$this->assertFalse( $this->get_platform()->prevent_not_allowed_files( false, $jpg, '/' ) );
		$this->assertTrue( $this->get_platform()->prevent_not_allowed_files( false, $exe, '/' ) );
	}

	/**
	 * Test if the S3 client is configured with the given credentials.
	 *
	 * @return void
	 */
	public function test_get_s3_client(): void {
		$this->set_platform_fields( $this->get_platform(), $this->field_values );
		$client = $this->get_platform()->get_s3_client();
		$this->assertInstanceOf( S3Client::class, $client );
		$this->assertEquals( 'eu-central-1', $client->getRegion() );

		$credentials = $client->getCredentials()->wait();
		$this->assertEquals( 'AKIAEXAMPLE', $credentials->getAccessKeyId() );
		$this->assertEquals( 'secret-example', $credentials->getSecretKey() );
	}

	/**
	 * Test the login with valid credentials.
	 *
	 * @return void
	 */
	public function test_do_login(): void {
		$s3 = $this->use_s3_mock( \ExternalFilesFromAwsS3\Platforms\AwsS3::class, array( 'ListObjectsV2' => array( 'Contents' => array() ) ) );
		$this->set_platform_fields( $s3, $this->field_values );

		$this->assertTrue( $s3->do_login( '/' ) );
		$this->assertEmpty( $s3->get_errors() );
		$this->assertEquals( array( 'ListObjectsV2' ), $this->get_s3_command_names() );
		$this->assertEquals( 'my-bucket', $this->get_s3_commands()[0]['Bucket'] );
	}

	/**
	 * Test the login with invalid credentials.
	 *
	 * @return void
	 */
	public function test_do_login_with_invalid_credentials(): void {
		$s3 = $this->use_s3_mock( \ExternalFilesFromAwsS3\Platforms\AwsS3::class, array( 'ListObjectsV2' => $this->get_s3_exception( 'ListObjectsV2', 403 ) ) );
		$this->set_platform_fields( $s3, $this->field_values );

		$this->assertFalse( $s3->do_login( '/' ) );
		$this->assertCount( 1, $s3->get_errors() );
		$this->assertStringContainsString( 'HTTP-Status 403', $this->get_error_messages( $s3 )[0] );

		// the error must be logged.
		$log = $this->get_log_entries();
		$this->assertNotEmpty( $log );
		$this->assertStringContainsString( 'HTTP-Status 403', $log[0]['log'] );
		$this->assertEquals( 'error', $log[0]['state'] );
	}

	/**
	 * Test the directory listing of a bucket.
	 *
	 * @return void
	 */
	public function test_get_directory_listing(): void {
		$date = new DateTimeResult( '2024-01-02T03:04:05Z' );
		$s3   = $this->use_s3_mock(
			\ExternalFilesFromAwsS3\Platforms\AwsS3::class,
			array(
				'ListObjectsV2' => array(
					'Contents' => array(
						array( 'Key' => 'example.jpg', 'Size' => 1234, 'LastModified' => $date ),
						array( 'Key' => 'documents/', 'Size' => 0, 'LastModified' => $date ),
						array( 'Key' => 'documents/2024/example.pdf', 'Size' => 5678, 'LastModified' => $date ),
						array( 'Key' => 'unknown.xyz', 'Size' => 1, 'LastModified' => $date ),
					),
				),
			)
		);
		$this->set_platform_fields( $s3, $this->field_values );

		$listing = $s3->get_directory_listing( '/' );
		$main    = 'https://console.aws.amazon.com/s3/buckets/my-bucket/';

		// check the main structure.
		$this->assertTrue( $listing['completed'] );
		$this->assertArrayHasKey( $main, $listing );
		$this->assertArrayHasKey( $main . 'documents/', $listing );
		$this->assertArrayHasKey( $main . 'documents/2024/', $listing );

		// the file in the main directory (the file with unknown file type is not listed).
		$this->assertCount( 1, $listing[ $main ]['files'] );
		$file = $listing[ $main ]['files'][0];
		$this->assertEquals( 'example.jpg', $file['title'] );
		$this->assertEquals( 'example.jpg', $file['s3_key'] );
		$this->assertEquals( 'https://my-bucket.s3.eu-central-1.amazonaws.com/example.jpg', $file['file'] );
		$this->assertEquals( 1234, $file['filesize'] );
		$this->assertEquals( 'image/jpeg', $file['mime-type'] );
		$this->assertNotEmpty( $file['last-modified'] );

		// the sub-directories.
		$this->assertEquals( 'documents', $listing[ $main . 'documents/' ]['title'] );
		$this->assertArrayHasKey( $main . 'documents/2024/', $listing[ $main . 'documents/' ]['dirs'] );
		$this->assertCount( 1, $listing[ $main . 'documents/2024/' ]['files'] );
		$this->assertEquals( 'documents/2024/example.pdf', $listing[ $main . 'documents/2024/' ]['files'][0]['s3_key'] );
		$this->assertEquals( 'application/pdf', $listing[ $main . 'documents/2024/' ]['files'][0]['mime-type'] );

		// the request must use the bucket.
		$this->assertEquals( 'my-bucket', $this->get_s3_commands()[0]['Bucket'] );
		$this->assertEmpty( $s3->get_errors() );
	}

	/**
	 * Test if files can be hidden in the directory listing via hook.
	 *
	 * @return void
	 */
	public function test_get_directory_listing_with_hidden_files(): void {
		$date = new DateTimeResult( '2024-01-02T03:04:05Z' );
		$s3   = $this->use_s3_mock(
			\ExternalFilesFromAwsS3\Platforms\AwsS3::class,
			array(
				'ListObjectsV2' => array(
					'Contents' => array(
						array( 'Key' => 'example.jpg', 'Size' => 1, 'LastModified' => $date ),
						array( 'Key' => 'example.pdf', 'Size' => 1, 'LastModified' => $date ),
					),
				),
			)
		);
		$this->set_platform_fields( $s3, $this->field_values );

		$callback = static function ( bool $hide, array $file ): bool {
			return 'example.pdf' === $file['Key'];
		};
		add_filter( 'efmlawss3_service_aws_s3_hide_file', $callback, 10, 2 );
		$listing = $s3->get_directory_listing( '/' );
		remove_filter( 'efmlawss3_service_aws_s3_hide_file', $callback );

		$files = $listing['https://console.aws.amazon.com/s3/buckets/my-bucket/']['files'];
		$this->assertCount( 1, $files );
		$this->assertEquals( 'example.jpg', $files[0]['title'] );
	}

	/**
	 * Test if only the requested directory is loaded from the bucket.
	 *
	 *
	 * @return void
	 */
	public function test_get_directory_listing_for_sub_directory(): void {
		$s3 = $this->use_s3_mock( \ExternalFilesFromAwsS3\Platforms\AwsS3::class, array( 'ListObjectsV2' => array( 'Contents' => array() ) ) );
		$this->set_platform_fields( $s3, $this->field_values );
		$s3->set_directory( 'https://console.aws.amazon.com/s3/buckets/my-bucket/documents/' );

		add_filter( 'efmlawss3_aws_s3_query_params', array( $s3, 'change_file_query' ) );
		$s3->get_directory_listing( 'https://console.aws.amazon.com/s3/buckets/my-bucket/documents/' );
		remove_filter( 'efmlawss3_aws_s3_query_params', array( $s3, 'change_file_query' ) );

		$command = $this->get_s3_commands()[0];
		$this->assertEquals( 'my-bucket', $command['Bucket'] );
		$this->assertEquals( 'documents/', $command['Prefix'] );
		$this->assertEquals( '/', $command['Delimiter'] );
	}

	/**
	 * Test the directory listing of an empty bucket.
	 *
	 * @return void
	 */
	public function test_get_directory_listing_without_files(): void {
		$s3 = $this->use_s3_mock( \ExternalFilesFromAwsS3\Platforms\AwsS3::class, array( 'ListObjectsV2' => array() ) );
		$this->set_platform_fields( $s3, $this->field_values );

		$this->assertEquals( array(), $s3->get_directory_listing( '/' ) );
		$this->assertEquals( array( 'No files returned from AWS S3.' ), $this->get_error_messages( $s3 ) );
	}

	/**
	 * Test the directory listing with invalid credentials.
	 *
	 * @return void
	 */
	public function test_get_directory_listing_with_error(): void {
		$s3 = $this->use_s3_mock( \ExternalFilesFromAwsS3\Platforms\AwsS3::class, array( 'ListObjectsV2' => $this->get_s3_exception( 'ListObjectsV2', 404, 'NoSuchBucket' ) ) );
		$this->set_platform_fields( $s3, $this->field_values );

		$this->assertEquals( array(), $s3->get_directory_listing( '/' ) );
		$this->assertStringContainsString( 'HTTP-Status 404', $this->get_error_messages( $s3 )[0] );
	}

	/**
	 * Test if a file is detected as public via its ACL.
	 *
	 * @return void
	 */
	public function test_is_file_public_available_via_acl(): void {
		$s3 = $this->use_s3_mock( \ExternalFilesFromAwsS3\Platforms\AwsS3::class, array( 'GetObjectAcl' => $this->get_acl_result( true ) ) );
		$this->set_platform_fields( $s3, $this->field_values );

		$this->assertTrue( $s3->is_file_public_available( 'example.jpg', $s3->get_s3_client() ) );
		$this->assertEquals( 'example.jpg', $this->get_s3_commands()[0]['Key'] );

		// no HTTP request is necessary.
		$this->assertEmpty( $this->get_http_requests() );
	}

	/**
	 * Test if a file is detected as public via an HTTP request, if the ACL does not allow it.
	 *
	 * This is the case for buckets with a bucket policy instead of ACLs.
	 *
	 * @return void
	 */
	public function test_is_file_public_available_via_http(): void {
		$s3 = $this->use_s3_mock( \ExternalFilesFromAwsS3\Platforms\AwsS3::class, array( 'GetObjectAcl' => $this->get_acl_result( false ) ) );
		$this->set_platform_fields( $s3, $this->field_values );

		// test 1: the file is public.
		$this->add_http_response( 'https://my-bucket.s3.eu-central-1.amazonaws.com/example.jpg', 200 );
		$this->assertTrue( $s3->is_file_public_available( 'example.jpg', $s3->get_s3_client() ) );
		$this->assertEquals( 'HEAD', $this->get_http_requests()[0]['method'] );

		// test 2: the file is not public.
		$this->add_http_response( 'https://my-bucket.s3.eu-central-1.amazonaws.com/example.jpg', 403 );
		$this->assertFalse( $s3->is_file_public_available( 'example.jpg', $s3->get_s3_client() ) );
	}

	/**
	 * Test the form title.
	 *
	 * @return void
	 */
	public function test_get_form_title(): void {
		$this->assertEquals( 'Connect to your AWS S3', $this->get_platform()->get_form_title() );
	}

	/**
	 * Test the form description in the different modes.
	 *
	 * @return void
	 */
	public function test_get_form_description(): void {
		// test 1: manual mode.
		update_option( 'eml_aws-s3_credentials_vault', 'manually' );
		$this->assertStringContainsString( 'https://docs.aws.amazon.com/', $this->get_platform()->get_form_description() );

		// test 2: user mode.
		update_option( 'eml_aws-s3_credentials_vault', 'user' );
		$this->assertStringContainsString( 'profile.php#efml-aws-s3', $this->get_platform()->get_form_description() );

		// test 3: global mode for administrators.
		update_option( 'eml_aws-s3_credentials_vault', 'global' );
		$this->do_login();
		$this->assertStringContainsString( 'already set', $this->get_platform()->get_form_description() );

		// test 4: global mode for users without the capability to manage options.
		$this->do_logout();
		$this->assertStringContainsString( 'by an administrator', $this->get_platform()->get_form_description() );
	}

	/**
	 * Test if the hooks are only registered for users with the capability for this platform.
	 *
	 * @return void
	 */
	public function test_init(): void {
		$platform = $this->get_platform();

		// test 1: without the capability.
		$platform->init();
		$this->assertFalse( has_filter( 'efml_protocols', array( $platform, 'add_protocol' ) ) );
		$this->assertFalse( has_filter( 'efml_http_check_content_type', array( $platform, 'allow_content_type' ) ) );

		// test 2: with the capability.
		$this->do_login();
		$platform->init();
		$this->assertIsInt( has_filter( 'efml_protocols', array( $platform, 'add_protocol' ) ) );
		$this->assertIsInt( has_filter( 'efml_directory_listing', array( $platform, 'prepare_tree_building' ) ) );
		$this->assertIsInt( has_filter( 'efml_http_header_args', array( $platform, 'remove_authorization_header' ) ) );
		$this->assertIsInt( has_filter( 'efml_http_check_content_type', array( $platform, 'allow_content_type' ) ) );
		$this->assertIsInt( has_filter( 'efml_files_check_content_type', array( $platform, 'allow_content_type' ) ) );
		$this->assertIsInt( has_filter( 'efml_external_file_infos', array( $platform, 'set_real_mime_type' ) ) );
		$this->assertIsInt( has_filter( 'efmlawss3_service_aws_s3_hide_file', array( $platform, 'prevent_not_allowed_files' ) ) );
		$this->assertIsInt( has_filter( 'efmlawss3_aws_s3_query_params', array( $platform, 'change_file_query' ) ) );
		$this->assertEquals( 'Choose file(s) from your AWS S3 server', $platform->get_title() );
	}

	/**
	 * Test if the settings for this platform are registered.
	 *
	 * @return void
	 */
	public function test_init_aws_s3(): void {
		$settings_obj = \ExternalFilesInMediaLibrary\Plugin\Settings::get_instance()->get_settings_obj();
		update_option( 'eml_aws-s3_credentials_vault', 'global' );

		// test 1: without the capability.
		$this->get_platform()->init_aws_s3();
		$this->assertFalse( $settings_obj->get_setting( 'eml_s3_access_key' ) );

		// test 2: with the capability.
		$this->do_login();
		$this->get_platform()->init_aws_s3();
		foreach ( array( 'eml_s3_access_key', 'eml_s3_secret_key', 'eml_s3_bucket', 'eml_s3_region', 'eml_s3_import_limit' ) as $setting_name ) {
			$this->assertInstanceOf( \easySettingsForWordPress\Setting::class, $settings_obj->get_setting( $setting_name ), 'Setting ' . $setting_name . ' is missing.' );
		}
		$this->assertEquals( 10, $settings_obj->get_setting( 'eml_s3_import_limit' )->get_default() );
	}

	/**
	 * Test if the user settings are only shown in the user mode.
	 *
	 * @return void
	 */
	public function test_add_user_settings(): void {
		$this->do_login();
		update_option( 'eml_user_settings', 1 );
		update_option( 'eml_user_settings_allowed_roles', array( 'administrator' ) );

		// test 1: not in user mode.
		update_option( 'eml_aws-s3_credentials_vault', 'global' );
		ob_start();
		$this->get_platform()->add_user_settings( wp_get_current_user() );
		$this->assertEmpty( ob_get_clean() );

		// test 2: in user mode.
		update_option( 'eml_aws-s3_credentials_vault', 'user' );
		ob_start();
		$this->get_platform()->add_user_settings( wp_get_current_user() );
		$output = (string) ob_get_clean();
		$this->assertStringContainsString( 'id="efml-aws-s3"', $output );
		$this->assertStringContainsString( 'efml-user-settings', $output );
	}

	/**
	 * Test which URLs are detected as URLs of AWS S3.
	 *
	 * @return void
	 */
	public function test_is_platform_url(): void {
		$this->assertTrue( $this->get_platform()->is_platform_url( 'https://my-bucket.s3.eu-central-1.amazonaws.com/example.jpg' ) );
		$this->assertFalse( $this->get_platform()->is_platform_url( 'https://example.com/example.jpg' ) );
	}
}
