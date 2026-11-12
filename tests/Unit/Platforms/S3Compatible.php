<?php
/**
 * Tests for class ExternalFilesFromAwsS3\Platforms\S3Compatible.
 *
 * @package external-files-from-aws-s3
 */

namespace ExternalFilesFromAwsS3\Tests\Unit\Platforms;

use Aws\Api\DateTimeResult;
use Aws\Command;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use ExternalFilesFromAwsS3\Platforms\HetznerObjectStorage;
use ExternalFilesFromAwsS3\Platforms\S3Compatible\Export;
use ExternalFilesFromAwsS3\Tests\externalFilesFromAwsS3Tests;
use ExternalFilesInMediaLibrary\ExternalFiles\Protocols\Http;
use ExternalFilesInMediaLibrary\Services\Service;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use WP_Error;

/**
 * Object to test functions in class ExternalFilesFromAwsS3\Platforms\S3Compatible.
 */
class S3Compatible extends externalFilesFromAwsS3Tests {
	/**
	 * The values for the fields we use in the tests.
	 *
	 * @var array<string,mixed>
	 */
	private array $field_values = array(
		'endpoint'   => 'https://s3.example.com',
		'region'     => 'eu-central-1',
		'access_key' => 'compatible-key',
		'secret'     => 'compatible-secret',
		'bucket'     => 'my-bucket',
		'path_style' => 1,
		'public_url' => '',
	);

	/**
	 * Return the object to test.
	 *
	 * @return \ExternalFilesFromAwsS3\Platforms\S3Compatible
	 */
	private function get_platform(): \ExternalFilesFromAwsS3\Platforms\S3Compatible {
		return \ExternalFilesFromAwsS3\Platforms\S3Compatible::get_instance();
	}

	/**
	 * Return an S3 exception with all details, which are returned by S3-compatible storages.
	 *
	 * @param string $command_name The name of the command.
	 * @param int    $status_code  The HTTP status code (0 for a failed connection).
	 * @param string $error_code   The S3 error code.
	 *
	 * @return S3Exception
	 */
	private function get_detailed_exception( string $command_name, int $status_code, string $error_code ): S3Exception {
		// a failed connection has no response.
		if ( 0 === $status_code ) {
			return new S3Exception(
				'Error executing "' . $command_name . '"',
				new Command( $command_name ),
				array(),
				new \RuntimeException( 'cURL error 6: Could not resolve host: s3.example.com' )
			);
		}

		// return an exception with the response.
		return new S3Exception(
			'Error executing "' . $command_name . '": ' . $error_code,
			new Command( $command_name ),
			array(
				'code'       => $error_code,
				'message'    => 'The request signature we calculated does not match the signature you provided.',
				'request_id' => 'REQUEST-ID-123',
				'response'   => new Response( $status_code, array( 'x-amz-id-2' => 'EXTENDED-ID-456' ) ),
			)
		);
	}

	/**
	 * Test if the returned object is always the same and not the object of a preset.
	 *
	 * @return void
	 */
	public function test_get_instance(): void {
		$instance = $this->get_platform();
		$this->assertInstanceOf( \ExternalFilesFromAwsS3\Platforms\S3Compatible::class, $instance );
		$this->assertInstanceOf( Service::class, $instance );
		$this->assertSame( $instance, \ExternalFilesFromAwsS3\Platforms\S3Compatible::get_instance() );
		$this->assertSame( \ExternalFilesFromAwsS3\Platforms\S3Compatible::class, get_class( $instance ) );

		// the preset has its own instance.
		$this->assertNotSame( $instance, HetznerObjectStorage::get_instance() );
		$this->assertSame( $instance, \ExternalFilesFromAwsS3\Platforms\S3Compatible::get_instance() );
	}

	/**
	 * Test if the returning variables are the expected strings.
	 *
	 * @return void
	 */
	public function test_get_name_and_label(): void {
		$this->assertEquals( 's3-compatible', $this->get_platform()->get_name() );
		$this->assertEquals( 'S3-compatible storage', $this->get_platform()->get_label() );
		$this->assertEquals( 'eml_s3_compatible_endpoint', $this->get_platform()->get_option_name( 'endpoint' ) );
		$this->assertEquals( 's3-compatible_endpoint', $this->get_platform()->get_user_setting_name( 'endpoint' ) );
	}

	/**
	 * Test the fields and their default values.
	 *
	 * @return void
	 */
	public function test_get_fields(): void {
		$fields = $this->get_platform()->get_fields();
		$this->assertEquals( array( 'endpoint', 'region', 'access_key', 'secret', 'bucket', 'path_style', 'public_url', 'checksum_when_required' ), array_keys( $fields ) );

		// the default values.
		$this->assertEquals( 'us-east-1', $fields['region']['value'] );
		$this->assertTrue( $fields['path_style']['value'] );
		$this->assertFalse( $fields['checksum_when_required']['value'] );

		// the optional fields.
		$this->assertTrue( $fields['path_style']['not_required'] );
		$this->assertTrue( $fields['public_url']['not_required'] );
		$this->assertTrue( $fields['checksum_when_required']['not_required'] );
		$this->assertArrayNotHasKey( 'not_required', $fields['endpoint'] );

		// the credentials.
		$this->assertTrue( $fields['access_key']['credential'] );
		$this->assertTrue( $fields['secret']['credential'] );
		$this->assertEquals( 'password', $fields['secret']['type'] );

		// nothing is readonly in the manual mode.
		$this->assertFalse( $fields['endpoint']['readonly'] );
		$this->assertFalse( $fields['path_style']['readonly'] );
	}

	/**
	 * Test the fields in the global mode.
	 *
	 * @return void
	 */
	public function test_get_fields_in_global_mode(): void {
		update_option( 'eml_s3-compatible_credentials_vault', 'global' );
		update_option( 'eml_s3_compatible_endpoint', 'minio.example.com:9000/' );
		update_option( 'eml_s3_compatible_region', 'eu-west-1' );
		update_option( 'eml_s3_compatible_access_key', $this->get_platform()->encrypt_value( 'global-key' ) );
		update_option( 'eml_s3_compatible_secret', $this->get_platform()->encrypt_value( 'global-secret' ) );
		update_option( 'eml_s3_compatible_bucket', 'global-bucket' );
		update_option( 'eml_s3_compatible_path_style', 0 );
		update_option( 'eml_s3_compatible_checksum_when_required', 1 );

		$fields = $this->get_platform()->get_fields();
		$this->assertEquals( 'global-key', $fields['access_key']['value'] );
		$this->assertEquals( 'global-secret', $fields['secret']['value'] );
		$this->assertEquals( 'global-bucket', $fields['bucket']['value'] );
		$this->assertFalse( $fields['path_style']['value'] );
		$this->assertTrue( $fields['checksum_when_required']['value'] );
		$this->assertTrue( $fields['access_key']['readonly'] );
		$this->assertTrue( $fields['path_style']['readonly'] );
		$this->assertFalse( $fields['public_url']['readonly'] );

		// the endpoint is normalized for the usage.
		$this->assertEquals( 'https://minio.example.com:9000', $this->get_platform()->get_endpoint( $fields ) );
		$this->assertEquals( 'https://global-bucket.minio.example.com:9000/', $this->get_platform()->get_url_mark() );
	}

	/**
	 * Test the fields in the user mode.
	 *
	 * @return void
	 */
	public function test_get_fields_in_user_mode(): void {
		update_option( 'eml_s3-compatible_credentials_vault', 'user' );
		$this->do_login();
		update_user_meta( 1, 'efml_s3-compatible_endpoint', $this->get_platform()->encrypt_value( 'https://s3.user.example.com' ) );
		update_user_meta( 1, 'efml_s3-compatible_access_key', $this->get_platform()->encrypt_value( 'user-key' ) );
		update_user_meta( 1, 'efml_s3-compatible_secret', $this->get_platform()->encrypt_value( 'user-secret' ) );
		update_user_meta( 1, 'efml_s3-compatible_bucket', $this->get_platform()->encrypt_value( 'user-bucket' ) );
		update_user_meta( 1, 'efml_s3-compatible_path_style', $this->get_platform()->encrypt_value( '0' ) );

		$fields = $this->get_platform()->get_fields();
		$this->assertEquals( 'user-key', $fields['access_key']['value'] );
		$this->assertEquals( 'us-east-1', $fields['region']['value'] );
		$this->assertFalse( $fields['path_style']['value'] );
		$this->assertEquals( 'https://user-bucket.s3.user.example.com/', $this->get_platform()->get_url_mark() );
	}

	/**
	 * Test the URL of the bucket.
	 *
	 * @return void
	 */
	public function test_get_bucket_url(): void {
		$platform = $this->get_platform();

		// test 1: path-style URLs.
		$fields = $this->set_platform_fields( $platform, $this->field_values );
		$this->assertEquals( 'https://s3.example.com/my-bucket/', $platform->get_bucket_url( $fields ) );
		$this->assertEquals( 'https://s3.example.com/my-bucket/', $platform->get_url_mark() );
		$this->assertEquals( 'https://s3.example.com/my-bucket/', $platform->get_directory() );

		// test 2: virtual-hosted URLs with port and HTTP.
		$fields = $this->set_platform_fields(
			$platform,
			array_merge(
				$this->field_values,
				array(
					'endpoint'   => 'http://localhost:9000/',
					'path_style' => '0',
				)
			)
		);
		$this->assertEquals( 'http://my-bucket.localhost:9000/', $platform->get_bucket_url( $fields ) );

		// test 3: the endpoint without scheme.
		$fields = $this->set_platform_fields( $platform, array_merge( $this->field_values, array( 'endpoint' => 's3.example.com' ) ) );
		$this->assertEquals( 'https://s3.example.com/my-bucket/', $platform->get_bucket_url( $fields ) );

		// test 4: the path-style setting as string from the form.
		$fields = $this->set_platform_fields( $platform, array_merge( $this->field_values, array( 'path_style' => 'false' ) ) );
		$this->assertFalse( $platform->is_path_style( $fields ) );
		$fields = $this->set_platform_fields( $platform, array_merge( $this->field_values, array( 'path_style' => 'true' ) ) );
		$this->assertTrue( $platform->is_path_style( $fields ) );

		// test 5: without endpoint or bucket.
		$fields = $this->set_platform_fields( $platform, array_merge( $this->field_values, array( 'endpoint' => '' ) ) );
		$this->assertEquals( '', $platform->get_bucket_url( $fields ) );
		$this->assertEquals( '/', $platform->get_directory() );
		$fields = $this->set_platform_fields( $platform, array_merge( $this->field_values, array( 'bucket' => '' ) ) );
		$this->assertEquals( '', $platform->get_bucket_url( $fields ) );
		$this->assertEquals( '/', $platform->get_directory() );
	}

	/**
	 * Test the public URL of files.
	 *
	 * @return void
	 */
	public function test_get_public_url_of_file(): void {
		$platform = $this->get_platform();

		// test 1: without public URL the URL of the bucket is used, the key is encoded.
		$fields = $this->set_platform_fields( $platform, $this->field_values );
		$this->assertEquals( 'https://s3.example.com/my-bucket/example.jpg', $platform->get_public_url_of_file( 'example.jpg', $fields ) );
		$this->assertEquals( 'https://s3.example.com/my-bucket/my%20folder/B%C3%A4r%20%281%29.jpg', $platform->get_public_url_of_file( 'my folder/Bär (1).jpg', $fields ) );

		// test 2: with a public URL (without scheme, with path and trailing slash).
		$fields = $this->set_platform_fields( $platform, array_merge( $this->field_values, array( 'public_url' => 'cdn.example.com/media/' ) ) );
		$this->assertEquals( 'https://cdn.example.com/media', $platform->get_public_base_url( $fields ) );
		$this->assertEquals( 'https://cdn.example.com/media/uploads/example.jpg', $platform->get_public_url_of_file( 'uploads/example.jpg', $fields ) );

		// test 3: the URL is filterable.
		$callback = static function ( string $url, string $key ): string {
			return 'https://filtered.example.com/' . $key;
		};
		add_filter( 'efmlawss3_s3_compatible_public_url', $callback, 10, 2 );
		$this->assertEquals( 'https://filtered.example.com/example.jpg', $platform->get_public_url_of_file( 'example.jpg', $fields ) );
		remove_filter( 'efmlawss3_s3_compatible_public_url', $callback );
	}

	/**
	 * Test the detection of URLs of the bucket and the key of a file.
	 *
	 * @return void
	 */
	public function test_get_requested_url_and_is_platform_url(): void {
		$platform = $this->get_platform();
		$fields   = $this->set_platform_fields( $platform, array_merge( $this->field_values, array( 'public_url' => 'https://cdn.example.com' ) ) );

		// test 1: the URL of the bucket.
		$this->assertTrue( $platform->is_platform_url( 'https://s3.example.com/my-bucket/example.jpg' ) );
		$this->assertTrue( $platform->is_platform_url( 'https://S3.EXAMPLE.COM/my-bucket/example.jpg' ) );
		$this->assertEquals( 'uploads/example.jpg', $platform->get_requested_url( 'https://s3.example.com/my-bucket/uploads/example.jpg', $fields ) );

		// test 2: the public URL.
		$this->assertTrue( $platform->is_platform_url( 'https://cdn.example.com/example.jpg' ) );
		$this->assertEquals( 'uploads/example.jpg', $platform->get_requested_url( 'https://cdn.example.com/uploads/example.jpg', $fields ) );

		// test 3: other URLs, also of another bucket on the same storage.
		$this->assertFalse( $platform->is_platform_url( 'https://example.com/example.jpg' ) );
		$this->assertFalse( $platform->is_platform_url( 'https://s3.example.com/other-bucket/example.jpg' ) );
		$this->assertFalse( $platform->is_platform_url( 'https://cdn.example.com.evil.com/example.jpg' ) );
		$this->assertEquals( 'https://example.com/example.jpg', $platform->get_requested_url( 'https://example.com/example.jpg', $fields ) );

		// test 4: without any fields nothing is detected.
		$this->assertFalse( $platform->is_url_of_fields( 'https://s3.example.com/my-bucket/example.jpg', array() ) );
	}

	/**
	 * Test the configuration of the S3 client.
	 *
	 * @return void
	 */
	public function test_get_client_configuration(): void {
		$platform = $this->get_platform();

		// test 1: the default configuration.
		$fields        = $this->set_platform_fields( $platform, $this->field_values );
		$configuration = $platform->get_client_configuration( $fields );
		$this->assertEquals( 'https://s3.example.com', $configuration['endpoint'] );
		$this->assertEquals( 'eu-central-1', $configuration['region'] );
		$this->assertTrue( $configuration['use_path_style_endpoint'] );
		$this->assertEquals( 'compatible-key', $configuration['credentials']['key'] );
		$this->assertEquals( 'compatible-secret', $configuration['credentials']['secret'] );
		$this->assertArrayNotHasKey( 'request_checksum_calculation', $configuration );
		$this->assertArrayNotHasKey( 'response_checksum_validation', $configuration );

		// test 2: checksums only if required.
		$fields        = $this->set_platform_fields( $platform, array_merge( $this->field_values, array( 'checksum_when_required' => 1 ) ) );
		$configuration = $platform->get_client_configuration( $fields );
		$this->assertEquals( 'when_required', $configuration['request_checksum_calculation'] );
		$this->assertEquals( 'when_required', $configuration['response_checksum_validation'] );

		// test 3: the region falls back to the default.
		$fields = $this->set_platform_fields( $platform, array_merge( $this->field_values, array( 'region' => '' ) ) );
		$this->assertEquals( 'us-east-1', $platform->get_client_configuration( $fields )['region'] );

		// test 4: the configuration is filterable.
		$callback = static function ( array $configuration ): array {
			$configuration['http']['connect_timeout'] = 3;
			return $configuration;
		};
		add_filter( 'efmlawss3_s3_compatible_client_configuration', $callback );
		$this->assertEquals( 3, $platform->get_client_configuration( $fields )['http']['connect_timeout'] );
		remove_filter( 'efmlawss3_s3_compatible_client_configuration', $callback );

		// test 5: the client uses the configuration.
		$this->set_platform_fields( $platform, $this->field_values );
		$client = $platform->get_s3_client();
		$this->assertInstanceOf( S3Client::class, $client );
		$this->assertEquals( 'eu-central-1', $client->getRegion() );
		$this->assertEquals( 'https://s3.example.com', (string) $client->getEndpoint() );
	}

	/**
	 * Test which checksum headers are sent by the client with and without the option to send them only if required.
	 *
	 * This test checks the real HTTP requests built by the AWS SDK.
	 *
	 * @return void
	 */
	public function test_checksum_headers(): void {
		$responder = static function ( RequestInterface $request ): Response {
			return new Response( 200, array( 'ETag' => '"abc"' ) );
		};

		// test 1: the default of the SDK sends a checksum for each upload.
		$requests = array();
		$s3       = $this->use_s3_http_mock( \ExternalFilesFromAwsS3\Platforms\S3Compatible::class, $responder, $requests );
		$this->set_platform_fields( $s3, $this->field_values );
		$s3->get_s3_client()->putObject(
			array(
				'Bucket' => 'my-bucket',
				'Key'    => 'example.txt',
				'Body'   => 'Example',
			)
		);
		$this->assertCount( 1, $requests );
		$this->assertEquals( 'https://s3.example.com/my-bucket/example.txt', (string) $requests[0]->getUri() );
		$this->assertNotEmpty( $this->get_checksum_headers( $requests[0] ), 'The SDK sends no checksum by default.' );

		// test 2: with the option, no checksum is sent for an upload, which does not require it.
		$requests = array();
		$s3       = $this->use_s3_http_mock( \ExternalFilesFromAwsS3\Platforms\S3Compatible::class, $responder, $requests );
		$this->set_platform_fields( $s3, array_merge( $this->field_values, array( 'checksum_when_required' => 1 ) ) );
		$s3->get_s3_client()->putObject(
			array(
				'Bucket' => 'my-bucket',
				'Key'    => 'example.txt',
				'Body'   => 'Example',
			)
		);
		$this->assertCount( 1, $requests );
		$this->assertEmpty( $this->get_checksum_headers( $requests[0] ), 'Checksum headers sent: ' . implode( ', ', $this->get_checksum_headers( $requests[0] ) ) );
	}

	/**
	 * Return the names of the checksum headers of the given request.
	 *
	 * @param RequestInterface $request The request.
	 *
	 * @return array<int,string>
	 */
	private function get_checksum_headers( RequestInterface $request ): array {
		// collect the headers.
		$headers = array();
		foreach ( array_keys( $request->getHeaders() ) as $name ) {
			if ( preg_match( '/^x-amz-(sdk-)?checksum|^x-amz-trailer$/i', (string) $name ) ) {
				$headers[] = strtolower( (string) $name );
			}
		}

		// return the list.
		return $headers;
	}

	/**
	 * Test the login.
	 *
	 * @return void
	 */
	public function test_do_login(): void {
		// test 1: valid credentials.
		$s3 = $this->use_s3_mock( \ExternalFilesFromAwsS3\Platforms\S3Compatible::class, array( 'ListObjectsV2' => array( 'Contents' => array() ) ) );
		$this->set_platform_fields( $s3, $this->field_values );
		$this->assertTrue( $s3->do_login( '/' ) );
		$this->assertEquals( 'my-bucket', $this->get_s3_commands()[0]['Bucket'] );

		// test 2: invalid credentials.
		$s3 = $this->use_s3_mock( \ExternalFilesFromAwsS3\Platforms\S3Compatible::class, array( 'ListObjectsV2' => $this->get_detailed_exception( 'ListObjectsV2', 403, 'SignatureDoesNotMatch' ) ) );
		$this->set_platform_fields( $s3, $this->field_values );
		$this->assertFalse( $s3->do_login( '/' ) );
		$this->assertStringContainsString( 'HTTP status 403', $this->get_error_messages( $s3 )[0] );
		$this->assertStringContainsString( 'Check the secret key and the region.', $this->get_error_messages( $s3 )[0] );

		// test 3: without endpoint.
		$this->set_platform_fields( $this->get_platform(), array_merge( $this->field_values, array( 'endpoint' => '' ) ) );
		$this->assertFalse( $this->get_platform()->do_login( '/' ) );
		$messages = $this->get_error_messages( $this->get_platform() );
		$this->assertStringContainsString( 'No credentials set', (string) end( $messages ) );
	}

	/**
	 * Test if failed requests are logged with all details, but without credentials.
	 *
	 * @return void
	 */
	public function test_log_of_failed_requests(): void {
		$s3 = $this->use_s3_mock( \ExternalFilesFromAwsS3\Platforms\S3Compatible::class, array( 'ListObjectsV2' => $this->get_detailed_exception( 'ListObjectsV2', 403, 'SignatureDoesNotMatch' ) ) );
		$this->set_platform_fields( $s3, $this->field_values );
		$this->assertFalse( $s3->do_login( '/' ) );

		// get the log entry.
		$entries = $this->get_log_entries( 'https://s3.example.com/my-bucket/' );
		$this->assertNotEmpty( $entries );
		$log = $entries[0]['log'];
		$this->assertEquals( 'error', $entries[0]['state'] );

		// check the details.
		foreach ( array( 'ListObjectsV2', '403', 'SignatureDoesNotMatch', 'The request signature we calculated', 'REQUEST-ID-123', 'EXTENDED-ID-456', 'https://s3.example.com', 'my-bucket', 'eu-central-1', 'S3-compatible storage' ) as $detail ) {
			$this->assertStringContainsString( $detail, $log );
		}

		// the credentials are never logged.
		$this->assertStringNotContainsString( 'compatible-secret', $log );
		$this->assertStringNotContainsString( 'compatible-key', $log );
	}

	/**
	 * Test if a failed connection is logged with the message of the connection error.
	 *
	 * @return void
	 */
	public function test_log_of_failed_connection(): void {
		$s3 = $this->use_s3_mock( \ExternalFilesFromAwsS3\Platforms\S3Compatible::class, array( 'ListObjectsV2' => $this->get_detailed_exception( 'ListObjectsV2', 0, '' ) ) );
		$this->set_platform_fields( $s3, $this->field_values );
		$this->assertFalse( $s3->do_login( '/' ) );
		$this->assertStringContainsString( 'The storage could not be reached.', $this->get_error_messages( $s3 )[0] );

		$entries = $this->get_log_entries( 'https://s3.example.com/my-bucket/' );
		$this->assertNotEmpty( $entries );
		$this->assertStringContainsString( 'Could not resolve host', $entries[0]['log'] );
	}

	/**
	 * Test that a not existing file is not logged as error during a check for its existence.
	 *
	 * @return void
	 */
	public function test_no_log_for_not_existing_files(): void {
		$s3 = $this->use_s3_mock( \ExternalFilesFromAwsS3\Platforms\S3Compatible::class, array( 'HeadObject' => $this->get_s3_exception( 'HeadObject', 404, 'NotFound' ) ) );
		$this->set_platform_fields( $s3, $this->field_values );
		$this->assertFalse( $s3->get_s3_client()->doesObjectExistV2( 'my-bucket', 'example.jpg' ) );
		$this->assertEmpty( $this->get_log_entries( 'https://s3.example.com/my-bucket/' ) );
	}

	/**
	 * Test the hints for errors.
	 *
	 * @return void
	 */
	public function test_get_hint_for_error(): void {
		$platform = $this->get_platform();
		$this->assertStringContainsString( 'endpoint URL', $platform->get_hint_for_error( 0, '' ) );
		$this->assertStringContainsString( 'access key', $platform->get_hint_for_error( 403, 'InvalidAccessKeyId' ) );
		$this->assertStringContainsString( 'secret key', $platform->get_hint_for_error( 403, 'SignatureDoesNotMatch' ) );
		$this->assertStringContainsString( 'region', $platform->get_hint_for_error( 400, 'AuthorizationHeaderMalformed' ) );
		$this->assertStringContainsString( 'path-style', $platform->get_hint_for_error( 404, 'NoSuchBucket' ) );
		$this->assertStringContainsString( 'permissions', $platform->get_hint_for_error( 403, 'AccessDenied' ) );
		$this->assertStringContainsString( 'checksums', $platform->get_hint_for_error( 400, 'InvalidArgument' ) );
		$this->assertStringContainsString( 'checksums', $platform->get_hint_for_error( 501, 'NotImplemented' ) );
		$this->assertStringContainsString( 'path-style', $platform->get_hint_for_error( 404, '' ) );
		$this->assertStringContainsString( 'provider', $platform->get_hint_for_error( 500, 'InternalError' ) );
	}

	/**
	 * Test the directory listing of a bucket.
	 *
	 * @return void
	 */
	public function test_get_directory_listing(): void {
		$date = new DateTimeResult( '2024-01-02T03:04:05Z' );
		$s3   = $this->use_s3_mock(
			\ExternalFilesFromAwsS3\Platforms\S3Compatible::class,
			array(
				'ListObjectsV2' => array(
					'Contents' => array(
						array( 'Key' => 'example.jpg', 'Size' => 1234, 'LastModified' => $date ),
						array( 'Key' => 'documents/example.pdf', 'Size' => 5678, 'LastModified' => $date ),
						array( 'Key' => 'hidden.jpg', 'Size' => 1, 'LastModified' => $date ),
					),
				),
			)
		);
		$this->set_platform_fields( $s3, $this->field_values );

		// hide a file and change the query via the hooks of this platform.
		$hide  = static function ( bool $hidden, array $file ): bool {
			return 'hidden.jpg' === $file['Key'] ? true : $hidden;
		};
		$query = static function ( array $query ): array {
			$query['MaxKeys'] = 500;
			return $query;
		};
		add_filter( 'efmlawss3_service_s3_compatible_hide_file', $hide, 10, 2 );
		add_filter( 'efmlawss3_s3_compatible_query_params', $query );

		$listing = $s3->get_directory_listing( '/' );

		remove_filter( 'efmlawss3_service_s3_compatible_hide_file', $hide );
		remove_filter( 'efmlawss3_s3_compatible_query_params', $query );

		// check the result.
		$main = 'https://s3.example.com/my-bucket/';
		$this->assertArrayHasKey( $main, $listing );
		$this->assertCount( 1, $listing[ $main ]['files'] );
		$this->assertEquals( 'https://s3.example.com/my-bucket/example.jpg', $listing[ $main ]['files'][0]['file'] );
		$this->assertEquals( 'documents/example.pdf', $listing[ $main . 'documents/' ]['files'][0]['s3_key'] );

		// check the query.
		$this->assertEquals( 'my-bucket', $this->get_s3_commands()[0]['Bucket'] );
		$this->assertEquals( 500, $this->get_s3_commands()[0]['MaxKeys'] );
	}

	/**
	 * Test the query for a subdirectory.
	 *
	 * @return void
	 */
	public function test_get_directory_listing_of_subdirectory(): void {
		$s3 = $this->use_s3_mock( \ExternalFilesFromAwsS3\Platforms\S3Compatible::class, array( 'ListObjectsV2' => array( 'Contents' => array() ) ) );
		$this->set_platform_fields( $s3, $this->field_values );
		$s3->set_directory( 'https://s3.example.com/my-bucket/documents/' );
		$s3->get_directory_listing( 'https://s3.example.com/my-bucket/documents/' );
		$this->assertEquals( 'documents/', $this->get_s3_commands()[0]['Prefix'] );
		$this->assertEquals( '/', $this->get_s3_commands()[0]['Delimiter'] );
	}

	/**
	 * Test if the public availability of a file is checked via HTTP and failures are logged if enabled.
	 *
	 * @return void
	 */
	public function test_is_file_public_available(): void {
		$platform = $this->get_platform();
		$this->set_platform_fields( $platform, $this->field_values );
		$client = $platform->get_s3_client();
		$url    = 'https://s3.example.com/my-bucket/example.jpg';

		// test 1: the file is public.
		$this->add_http_response( $url, 200 );
		$this->assertTrue( $platform->is_file_public_available( 'example.jpg', $client ) );
		$this->assertEquals( 'HEAD', $this->get_http_requests()[0]['method'] );

		// test 2: the file is not public, nothing is logged without enabled log.
		$this->add_http_response( $url, 403 );
		$this->assertFalse( $platform->is_file_public_available( 'example.jpg', $client ) );
		$this->assertEmpty( $this->get_log_entries( $url ) );

		// test 3: the file is not public, the details are logged.
		$platform->set_public_check_logging( true );
		$this->assertFalse( $platform->is_file_public_available( 'example.jpg', $client ) );
		$entries = $this->get_log_entries( $url );
		$this->assertNotEmpty( $entries );
		$this->assertStringContainsString( '403', $entries[0]['log'] );
		$this->assertStringContainsString( $url, $entries[0]['log'] );

		// test 4: the request failed.
		$error = static function (): WP_Error {
			return new WP_Error( 'http_request_failed', 'Connection timed out' );
		};
		add_filter( 'pre_http_request', $error, 5 );
		$this->assertFalse( $platform->is_file_public_available( 'example.jpg', $client ) );
		remove_filter( 'pre_http_request', $error, 5 );
		$this->assertStringContainsString( 'Connection timed out', $this->get_log_entries( $url )[0]['log'] );
		$platform->set_public_check_logging( false );
	}

	/**
	 * Test the complete connection test with success.
	 *
	 * @return void
	 */
	public function test_run_connection_test(): void {
		$s3 = $this->use_s3_mock(
			\ExternalFilesFromAwsS3\Platforms\S3Compatible::class,
			array(
				'ListObjectsV2' => array( 'Contents' => array() ),
				'PutObject'     => array( 'ObjectURL' => 'https://s3.example.com/my-bucket/test.txt' ),
				'DeleteObject'  => array(),
			)
		);
		$this->set_platform_fields( $s3, $this->field_values );
		$callback = $this->add_http_response_for_prefix( 'https://s3.example.com/my-bucket/efml-connection-test-', 200 );

		$steps = $s3->run_connection_test();
		remove_filter( 'pre_http_request', $callback, 5 );

		// check the steps.
		$this->assertCount( 4, $steps );
		foreach ( $steps as $step ) {
			$this->assertTrue( $step['success'], $step['title'] . ': ' . $step['message'] );
			$this->assertFalse( $step['skipped'] );
		}

		// check the commands: the test file is uploaded and deleted again.
		$this->assertEquals( array( 'ListObjectsV2', 'PutObject', 'DeleteObject' ), $this->get_s3_command_names() );
		$commands = $this->get_s3_commands();
		$this->assertStringStartsWith( 'efml-connection-test-', $commands[1]['Key'] );
		$this->assertEquals( 'text/plain', $commands[1]['ContentType'] );
		$this->assertEquals( $commands[1]['Key'], $commands[2]['Key'] );
		$this->assertEquals( 'HEAD', $this->get_http_requests()[0]['method'] );

		// each step is logged.
		$entries = $this->get_log_entries( 'https://s3.example.com/my-bucket/' );
		$this->assertCount( 5, $entries );
		$this->assertEquals( 'success', $entries[0]['state'] );
	}

	/**
	 * Test the connection test, if the bucket could not be listed: all other steps are skipped.
	 *
	 * @return void
	 */
	public function test_run_connection_test_with_failed_login(): void {
		$s3 = $this->use_s3_mock( \ExternalFilesFromAwsS3\Platforms\S3Compatible::class, array( 'ListObjectsV2' => $this->get_detailed_exception( 'ListObjectsV2', 404, 'NoSuchBucket' ) ) );
		$this->set_platform_fields( $s3, $this->field_values );

		$steps = $s3->run_connection_test();
		$this->assertFalse( $steps[0]['success'] );
		$this->assertStringContainsString( 'NoSuchBucket', $steps[0]['message'] );
		$this->assertTrue( $steps[1]['skipped'] );
		$this->assertTrue( $steps[2]['skipped'] );
		$this->assertTrue( $steps[3]['skipped'] );
		$this->assertEquals( array( 'ListObjectsV2' ), $this->get_s3_command_names() );
	}

	/**
	 * Test the connection test, if the file is not public available: the test file is deleted anyway.
	 *
	 * @return void
	 */
	public function test_run_connection_test_without_public_access(): void {
		$s3 = $this->use_s3_mock(
			\ExternalFilesFromAwsS3\Platforms\S3Compatible::class,
			array(
				'ListObjectsV2' => array( 'Contents' => array() ),
				'PutObject'     => array( 'ObjectURL' => 'https://s3.example.com/my-bucket/test.txt' ),
				'DeleteObject'  => array(),
			)
		);
		$this->set_platform_fields( $s3, $this->field_values );
		$callback = $this->add_http_response_for_prefix( 'https://s3.example.com/my-bucket/efml-connection-test-', 403 );

		$steps = $s3->run_connection_test();
		remove_filter( 'pre_http_request', $callback, 5 );

		$this->assertTrue( $steps[1]['success'] );
		$this->assertFalse( $steps[2]['success'] );
		$this->assertStringContainsString( 'not public available', $steps[2]['message'] );
		$this->assertTrue( $steps[3]['success'] );
		$this->assertEquals( array( 'ListObjectsV2', 'PutObject', 'DeleteObject' ), $this->get_s3_command_names() );
	}

	/**
	 * Test the connection test, if the upload fails.
	 *
	 * @return void
	 */
	public function test_run_connection_test_with_failed_upload(): void {
		$s3 = $this->use_s3_mock(
			\ExternalFilesFromAwsS3\Platforms\S3Compatible::class,
			array(
				'ListObjectsV2' => array( 'Contents' => array() ),
				'PutObject'     => $this->get_detailed_exception( 'PutObject', 400, 'InvalidArgument' ),
			)
		);
		$this->set_platform_fields( $s3, $this->field_values );

		$steps = $s3->run_connection_test();
		$this->assertTrue( $steps[0]['success'] );
		$this->assertFalse( $steps[1]['success'] );
		$this->assertStringContainsString( 'checksums', $steps[1]['message'] );
		$this->assertTrue( $steps[2]['skipped'] );
		$this->assertTrue( $steps[3]['skipped'] );
	}

	/**
	 * Test the connection test without endpoint.
	 *
	 * @return void
	 */
	public function test_run_connection_test_without_endpoint(): void {
		$this->set_platform_fields( $this->get_platform(), array_merge( $this->field_values, array( 'endpoint' => '' ) ) );
		$steps = $this->get_platform()->run_connection_test();
		$this->assertFalse( $steps[0]['success'] );
		$this->assertFalse( $steps[0]['skipped'] );
		$this->assertTrue( $steps[1]['skipped'] );
	}

	/**
	 * Test the details of the connection for the log.
	 *
	 * @return void
	 */
	public function test_get_log_context(): void {
		$this->set_platform_fields( $this->get_platform(), array_merge( $this->field_values, array( 'public_url' => 'https://cdn.example.com' ) ) );
		$context = $this->get_platform()->get_log_context();
		$this->assertEquals( 'S3-compatible storage', $context['Platform'] );
		$this->assertEquals( 'my-bucket', $context['Bucket'] );
		$this->assertEquals( 'https://s3.example.com', $context['Endpoint'] );
		$this->assertEquals( 'eu-central-1', $context['Region'] );
		$this->assertEquals( 'Yes', $context['Path-style URLs'] );
		$this->assertEquals( 'No', $context['Checksums only if required'] );
		$this->assertEquals( 'https://cdn.example.com', $context['Public URL'] );
		$this->assertNotContains( 'compatible-secret', $context );
	}

	/**
	 * Test the user settings for this platform.
	 *
	 * @return void
	 */
	public function test_get_user_settings(): void {
		$settings = $this->get_platform()->get_user_settings();
		foreach ( array( 'endpoint', 'region', 'access_key', 'secret', 'bucket', 'path_style', 'public_url', 'checksum_when_required' ) as $field_name ) {
			$this->assertArrayHasKey( 's3-compatible_' . $field_name, $settings );
		}
		$this->assertEquals( 'password', $settings['s3-compatible_secret']['field'] );
		$this->assertEquals( 'checkbox', $settings['s3-compatible_path_style']['field'] );
		$this->assertEquals( 'us-east-1', $settings['s3-compatible_region']['default'] );

		// the list of settings is filterable.
		$callback = static function ( array $list ): array {
			$list['custom'] = array( 'label' => 'Custom' );
			return $list;
		};
		add_filter( 'efmlawss3_s3_compatible_user_settings', $callback );
		$this->assertArrayHasKey( 'custom', $this->get_platform()->get_user_settings() );
		remove_filter( 'efmlawss3_s3_compatible_user_settings', $callback );
	}

	/**
	 * Test the user settings in the profile.
	 *
	 * @return void
	 */
	public function test_add_user_settings(): void {
		update_option( 'eml_s3-compatible_credentials_vault', 'user' );
		$this->do_login();
		ob_start();
		$this->get_platform()->add_user_settings( wp_get_current_user() );
		$output = (string) ob_get_clean();
		$this->assertStringContainsString( 'S3-compatible storage', $output );
		$this->assertStringContainsString( 'efml_s3-compatible_endpoint', $output );
	}

	/**
	 * Test if the export object and the configuration are returned.
	 *
	 * @return void
	 */
	public function test_get_export_and_configuration_object(): void {
		$this->assertInstanceOf( Export::class, $this->get_platform()->get_export_object() );
		$this->assertSame( Export::class, get_class( $this->get_platform()->get_export_object() ) );
		$this->assertSame( $this->get_platform(), $this->call_method( $this->get_platform()->get_export_object(), 'get_directory_listing_object' ) );
		$this->assertEquals( 'ExternalFilesFromAwsS3\Platforms\S3Compatible\Configuration', $this->get_platform()->get_configuration_object_name() );

		$configuration = new \ExternalFilesFromAwsS3\Platforms\S3Compatible\Configuration();
		$this->assertEquals( 'Use only S3-compatible storage', $configuration->get_title() );
		$this->assertStringContainsString( 'S3-compatible storage', $configuration->get_dialog_hints()[0] );
	}

	/**
	 * Test the actions for files and directories.
	 *
	 * @return void
	 */
	public function test_get_actions(): void {
		$this->set_platform_fields( $this->get_platform(), $this->field_values );
		$actions = $this->get_platform()->get_actions();
		$this->assertStringContainsString( '"service": "s3-compatible"', $actions[0]['action'] );

		$global_actions = $this->call_method( $this->get_platform(), 'get_global_actions' );
		$this->assertIsArray( $global_actions );
		$labels = array_column( $global_actions, 'label' );
		$this->assertStringContainsString( 'https://s3.example.com/my-bucket/', $global_actions[ array_search( 'Import active directory', $labels, true ) ]['action'] );
		$this->assertStringContainsString( 'efmlawss3_connection_test( "s3-compatible"', $global_actions[ array_search( 'Run complete connection test', $labels, true ) ]['action'] );
	}

	/**
	 * Test the content type check and the mime type for URLs of this platform.
	 *
	 * @return void
	 */
	public function test_allow_content_type_and_set_real_mime_type(): void {
		$this->set_platform_fields( $this->get_platform(), $this->field_values );
		$this->assertFalse( $this->get_platform()->allow_content_type( true, 'https://s3.example.com/my-bucket/example.jpg' ) );
		$this->assertTrue( $this->get_platform()->allow_content_type( true, 'https://example.com/example.jpg' ) );
		$this->assertEquals( 'image/jpeg', $this->get_platform()->set_real_mime_type( array( 'mime-type' => 'application/octet-stream' ), 'https://s3.example.com/my-bucket/example.jpg' )['mime-type'] );
		$this->assertEquals( 'text/html', $this->get_platform()->set_real_mime_type( array( 'mime-type' => 'text/html' ), 'https://example.com/example.jpg' )['mime-type'] );
	}

	/**
	 * Test if the authorization header is only removed for URLs of this platform.
	 *
	 * @return void
	 */
	public function test_remove_authorization_header(): void {
		$this->set_platform_fields( $this->get_platform(), $this->field_values );
		$args = array( 'headers' => array( 'Authorization' => 'Basic abc' ) );
		$this->assertArrayNotHasKey( 'Authorization', $this->get_platform()->remove_authorization_header( $args, new Http( 'https://s3.example.com/my-bucket/example.jpg' ) )['headers'] );
		$this->assertArrayHasKey( 'Authorization', $this->get_platform()->remove_authorization_header( $args, new Http( 'https://example.com/example.jpg' ) )['headers'] );
	}

	/**
	 * Test the form title and description.
	 *
	 * @return void
	 */
	public function test_get_form_title_and_description(): void {
		$this->assertEquals( 'Connect to your S3-compatible storage bucket', $this->get_platform()->get_form_title() );

		update_option( 'eml_s3-compatible_credentials_vault', 'manually' );
		$this->assertStringContainsString( 'docs/s3-compatible.md', $this->get_platform()->get_form_description() );

		update_option( 'eml_s3-compatible_credentials_vault', 'user' );
		$this->assertStringContainsString( 'profile.php#efml-s3-compatible', $this->get_platform()->get_form_description() );
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
		$this->assertIsInt( has_action( 'init', array( $platform, 'init_settings' ) ) );
		$this->assertIsInt( has_filter( 'efml_http_check_content_type', array( $platform, 'allow_content_type' ) ) );
		$this->assertIsInt( has_filter( 'efml_external_file_infos', array( $platform, 'set_real_mime_type' ) ) );
	}

	/**
	 * Test if the settings for this platform are registered in the global mode.
	 *
	 * @return void
	 */
	public function test_init_settings(): void {
		$settings_obj = \ExternalFilesInMediaLibrary\Plugin\Settings::get_instance()->get_settings_obj();
		update_option( 'eml_s3-compatible_credentials_vault', 'global' );
		$this->do_login();
		$this->get_platform()->add_base_settings();
		$this->get_platform()->init_settings();
		foreach ( array( 'endpoint', 'region', 'access_key', 'secret', 'bucket', 'path_style', 'public_url', 'checksum_when_required' ) as $field_name ) {
			$this->assertInstanceOf( \easySettingsForWordPress\Setting::class, $settings_obj->get_setting( 'eml_s3_compatible_' . $field_name ), 'Setting ' . $field_name . ' is missing.' );
		}
	}

	/**
	 * Test if the export logs the details of a failed check of the public availability.
	 *
	 * @return void
	 */
	public function test_export_logs_public_check(): void {
		$attachment_id = $this->create_local_attachment();
		$file_path     = (string) get_attached_file( $attachment_id, true );
		$url           = 'https://s3.example.com/my-bucket/' . basename( $file_path );
		$this->add_http_response( $url, 403 );
		$s3     = $this->use_s3_mock( \ExternalFilesFromAwsS3\Platforms\S3Compatible::class, array( 'PutObject' => array( 'ObjectURL' => $url ) ) );
		$fields = $this->set_platform_fields( $s3, $this->field_values );

		$result = Export::get_instance()->export_file(
			$attachment_id,
			'https://s3.example.com/my-bucket/',
			array(
				'fields'    => $fields,
				'directory' => 'https://s3.example.com/my-bucket/',
			)
		);
		$this->assertFalse( $result );

		// the details are logged.
		$entries = $this->get_log_entries( $url );
		$this->assertNotEmpty( $entries );
		$this->assertStringContainsString( '403', $entries[0]['log'] );

		// the detailed log is disabled after the export.
		$this->assertFalse( $s3->is_file_public_available( basename( $file_path ), $s3->get_s3_client() ) );
		$this->assertCount( count( $entries ), $this->get_log_entries( $url ) );
	}
}
