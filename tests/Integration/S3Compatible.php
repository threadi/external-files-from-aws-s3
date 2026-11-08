<?php
/**
 * Integration tests for the S3-compatible storage against a real S3 server.
 *
 * These tests need a running S3-compatible server. Its endpoint is set in the environment variable
 * EFMLAWSS3_INTEGRATION_ENDPOINT (e.g. "http://127.0.0.1:9000"), the credentials could be set in
 * EFMLAWSS3_INTEGRATION_ACCESS_KEY and EFMLAWSS3_INTEGRATION_SECRET. Without the endpoint the tests are skipped.
 *
 * Some servers (e.g. Moto, which is used in the GitHub workflow) do not use the bucket policy for anonymous requests.
 * For them set EFMLAWSS3_INTEGRATION_PUBLIC_ACL=1, so the uploads of these tests use the ACL "public-read"
 * instead of "private" like the export of this plugin does it.
 *
 * Run them with: composer test-integration
 *
 * @package external-files-from-aws-s3
 */

namespace ExternalFilesFromAwsS3\Tests\Integration;

use Aws\CommandInterface;
use Aws\Middleware;
use Aws\S3\S3Client;
use ExternalFilesFromAwsS3\Platforms\S3Compatible\Export;
use ExternalFilesFromAwsS3\Platforms\S3Compatible\Protocol;
use ExternalFilesFromAwsS3\Tests\externalFilesFromAwsS3Tests;

/**
 * Object to test the S3-compatible storage against a real S3 server.
 */
class S3Compatible extends externalFilesFromAwsS3Tests {
	/**
	 * The name of the bucket for the actual test.
	 *
	 * @var string
	 */
	private string $bucket = '';

	/**
	 * Prepare each test: create a new bucket and allow HTTP requests to the server.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		// bail if no server is configured.
		if ( ! $this->get_endpoint() ) {
			$this->markTestSkipped( 'Set the environment variable EFMLAWSS3_INTEGRATION_ENDPOINT to run the integration tests.' );
		}

		// allow the HTTP requests to the server.
		remove_filter( 'pre_http_request', array( externalFilesFromAwsS3Tests::class, 'filter_http_requests' ) );

		// use a public ACL for each upload, if the server does not use the bucket policy.
		if ( getenv( 'EFMLAWSS3_INTEGRATION_PUBLIC_ACL' ) ) {
			$this->use_public_acl();
		}

		// create a new bucket for this test.
		$this->bucket = 'efml-test-' . strtolower( wp_generate_password( 8, false ) );
		$this->set_platform_fields( $this->get_platform(), $this->get_values() );
		$this->get_platform()->get_s3_client()->createBucket( array( 'Bucket' => $this->bucket ) );

		// make the bucket public readable, like it is necessary for the export.
		$this->get_platform()->get_s3_client()->putBucketPolicy(
			array(
				'Bucket' => $this->bucket,
				'Policy' => (string) wp_json_encode(
					array(
						'Version'   => '2012-10-17',
						'Statement' => array(
							array(
								'Effect'    => 'Allow',
								'Principal' => '*',
								'Action'    => array( 's3:GetObject' ),
								'Resource'  => array( 'arn:aws:s3:::' . $this->bucket . '/*' ),
							),
						),
					)
				),
			)
		);
	}

	/**
	 * Cleanup after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		add_filter( 'pre_http_request', array( externalFilesFromAwsS3Tests::class, 'filter_http_requests' ), 10, 3 );
		$this->restore_platform();
		parent::tear_down();
	}

	/**
	 * Replace the platform with a mock, whose S3 client uploads all files with the ACL "public-read".
	 *
	 * @return void
	 */
	private function use_public_acl(): void {
		$mock = $this->getMockBuilder( \ExternalFilesFromAwsS3\Platforms\S3Compatible::class )
			->disableOriginalConstructor()
			->onlyMethods( array( 'get_the_client' ) )
			->getMock();
		$mock->method( 'get_the_client' )->willReturnCallback(
			static function ( array $configuration ): S3Client {
				$client = new S3Client( $configuration );
				$client->getHandlerList()->appendInit(
					Middleware::mapCommand(
						static function ( CommandInterface $command ): CommandInterface {
							if ( isset( $command['ACL'] ) ) {
								$command['ACL'] = 'public-read';
							}
							return $command;
						}
					),
					'efmlawss3_integration_public_acl'
				);
				return $client;
			}
		);
		$this->set_property_static( \ExternalFilesFromAwsS3\Platforms\S3Compatible::class, 'instance', $mock );
	}

	/**
	 * Restore the original platform.
	 *
	 * @return void
	 */
	private function restore_platform(): void {
		$this->set_property_static( \ExternalFilesFromAwsS3\Platforms\S3Compatible::class, 'instance', null );
	}

	/**
	 * Set a static property of the given class.
	 *
	 * @param string $class_name The class name.
	 * @param string $property   The name of the property.
	 * @param mixed  $value      The value.
	 *
	 * @return void
	 */
	private function set_property_static( string $class_name, string $property, mixed $value ): void {
		( new \ReflectionClass( $class_name ) )->setStaticPropertyValue( $property, $value );
	}

	/**
	 * Return the endpoint of the server.
	 *
	 * @return string
	 */
	private function get_endpoint(): string {
		return (string) getenv( 'EFMLAWSS3_INTEGRATION_ENDPOINT' );
	}

	/**
	 * Return the values for the fields.
	 *
	 * @param array<string,mixed> $values Values to change.
	 *
	 * @return array<string,mixed>
	 */
	private function get_values( array $values = array() ): array {
		return array_merge(
			array(
				'endpoint'   => $this->get_endpoint(),
				'region'     => 'us-east-1',
				'access_key' => getenv( 'EFMLAWSS3_INTEGRATION_ACCESS_KEY' ) ? (string) getenv( 'EFMLAWSS3_INTEGRATION_ACCESS_KEY' ) : 'testing',
				'secret'     => getenv( 'EFMLAWSS3_INTEGRATION_SECRET' ) ? (string) getenv( 'EFMLAWSS3_INTEGRATION_SECRET' ) : 'testing',
				'bucket'     => $this->bucket,
				'path_style' => 1,
			),
			$values
		);
	}

	/**
	 * Return the platform.
	 *
	 * @return \ExternalFilesFromAwsS3\Platforms\S3Compatible
	 */
	private function get_platform(): \ExternalFilesFromAwsS3\Platforms\S3Compatible {
		return \ExternalFilesFromAwsS3\Platforms\S3Compatible::get_instance();
	}

	/**
	 * Test the login and the directory listing.
	 *
	 * @return void
	 */
	public function test_login_and_listing(): void {
		$platform = $this->get_platform();
		$this->assertTrue( $platform->do_login( '/' ), implode( ' ', $this->get_error_messages( $platform ) ) );

		// upload a file and list it.
		$platform->get_s3_client()->putObject(
			array(
				'Bucket'      => $this->bucket,
				'Key'         => 'dir/example.jpg',
				'Body'        => (string) file_get_contents( UNIT_TESTS_DATA_PLUGIN_DIR . 'example.jpg' ), // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
				'ContentType' => 'image/jpeg',
			)
		);
		$listing = $platform->get_directory_listing( '/' );
		$main    = rtrim( $this->get_endpoint(), '/' ) . '/' . $this->bucket . '/';
		$this->assertArrayHasKey( $main . 'dir/', $listing );
		$this->assertEquals( $main . 'dir/example.jpg', $listing[ $main . 'dir/' ]['files'][0]['file'] );
	}

	/**
	 * Test the complete connection test with and without checksums.
	 *
	 * @return void
	 */
	public function test_connection_test(): void {
		foreach ( array( 0, 1 ) as $checksum_when_required ) {
			$this->set_platform_fields( $this->get_platform(), $this->get_values( array( 'checksum_when_required' => $checksum_when_required ) ) );
			foreach ( $this->get_platform()->run_connection_test() as $step ) {
				$this->assertTrue( $step['success'], $step['title'] . ': ' . $step['message'] );
			}
		}

		// the test file has been deleted.
		$result = $this->get_platform()->get_s3_client()->listObjectsV2( array( 'Bucket' => $this->bucket ) );
		$this->assertEmpty( $result['Contents'] );
	}

	/**
	 * Test the export, the import and the deletion of a file.
	 *
	 * @return void
	 */
	public function test_export_import_and_delete(): void {
		$fields        = $this->set_platform_fields( $this->get_platform(), $this->get_values() );
		$directory     = $this->get_platform()->get_directory();
		$credentials   = array(
			'fields'    => $fields,
			'directory' => $directory,
		);
		$attachment_id = $this->create_local_attachment();
		$file_name     = basename( (string) get_attached_file( $attachment_id, true ) );
		$export        = Export::get_instance();

		// test 1: the export.
		$this->assertFalse( $export->file_exists_on_target( $directory . $file_name, $credentials ) );
		$url = $export->export_file( $attachment_id, $directory, $credentials );
		$this->assertEquals( $directory . $file_name, $url );
		$this->assertTrue( $export->file_exists_on_target( $directory . $file_name, $credentials ) );
		$this->assertEquals( 200, wp_remote_retrieve_response_code( wp_remote_head( (string) $url ) ) );
		$this->assertEquals( 'image/jpeg', wp_remote_retrieve_header( wp_remote_head( (string) $url ), 'content-type' ) );

		// test 2: the import of the exported file.
		$protocol = new Protocol( (string) $url );
		$protocol->set_fields( $fields );
		$this->assertTrue( $protocol->is_url_compatible() );
		$results = $protocol->get_url_infos();
		$this->assertCount( 1, $results );
		$this->assertFalse( $results[0]['local'] );
		$this->assertEquals( 'image/jpeg', $results[0]['mime-type'] );
		$this->assertFileExists( $results[0]['tmp-file'] );
		$this->assertEquals( filesize( UNIT_TESTS_DATA_PLUGIN_DIR . 'example.jpg' ), filesize( $results[0]['tmp-file'] ) );
		wp_delete_file( $results[0]['tmp-file'] );

		// test 3: the deletion.
		$this->assertTrue( $export->delete_exported_file( (string) $url, $credentials, $attachment_id ) );
		$this->assertFalse( $export->file_exists_on_target( $directory . $file_name, $credentials ) );
	}

	/**
	 * Test the errors with a not existing bucket: the login fails and the error is logged.
	 *
	 * @return void
	 */
	public function test_not_existing_bucket(): void {
		$this->set_platform_fields( $this->get_platform(), $this->get_values( array( 'bucket' => 'efml-not-existing-bucket' ) ) );
		$this->assertFalse( $this->get_platform()->do_login( '/' ) );
		$this->assertStringContainsString( 'NoSuchBucket', $this->get_error_messages( $this->get_platform() )[0] );
		$entries = $this->get_log_entries( rtrim( $this->get_endpoint(), '/' ) . '/efml-not-existing-bucket/' );
		$this->assertNotEmpty( $entries );
		$this->assertStringContainsString( 'ListObjectsV2', $entries[0]['log'] );
	}
}
