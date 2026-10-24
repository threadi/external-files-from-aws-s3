<?php
/**
 * Tests for class ExternalFilesFromAwsS3\Protocol_Base.
 *
 * The tests for get_url_infos() run for each platform, as each platform uses its own URLs and its own way to
 * check whether a file is public available.
 *
 * @package external-files-from-aws-s3
 */

namespace ExternalFilesFromAwsS3\Tests\Unit;

use Aws\Api\DateTimeResult;
use Aws\CommandInterface;
use ExternalFilesFromAwsS3\Platform_Base;
use ExternalFilesFromAwsS3\Platforms\AwsS3;
use ExternalFilesFromAwsS3\Platforms\BackblazeS3;
use ExternalFilesFromAwsS3\Platforms\CloudflareR2;
use ExternalFilesFromAwsS3\Platforms\DigitalOceanSpaces;
use ExternalFilesFromAwsS3\Tests\externalFilesFromAwsS3Tests;

/**
 * Object to test functions in class ExternalFilesFromAwsS3\Protocol_Base.
 */
class Protocol_Base extends externalFilesFromAwsS3Tests {
	/**
	 * List of temporary files created during the actual test.
	 *
	 * @var array<int,string>
	 */
	private array $tmp_files = array();

	/**
	 * Cleanup after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		foreach ( $this->tmp_files as $tmp_file ) {
			if ( file_exists( $tmp_file ) ) {
				wp_delete_file( $tmp_file );
			}
		}
		$this->tmp_files = array();
		parent::tear_down();
	}

	/**
	 * Return the configuration of each platform for the tests.
	 *
	 * Each entry contains:
	 * - the class names of the platform and the protocol.
	 * - the values for the fields.
	 * - the URL of a single file in the bucket (as used in the media library) and its key.
	 * - the URL of the main directory of the bucket.
	 * - a callback, which sets the responses to mark a file as public or private.
	 * - the public URL of the file.
	 *
	 * @return array<string,array<int,mixed>>
	 */
	public function platforms_provider(): array {
		return array(
			'AWS S3'              => array(
				AwsS3::class,
				AwsS3\Protocol::class,
				array(
					'access_key' => 'key',
					'secret'     => 'secret',
					'bucket'     => 'my-bucket',
					'region'     => 'eu-central-1',
				),
				'https://my-bucket.s3.eu-central-1.amazonaws.com/dir/example.jpg',
				'https://console.aws.amazon.com/s3/buckets/my-bucket/',
				'https://my-bucket.s3.eu-central-1.amazonaws.com/',
			),
			'Backblaze S3'        => array(
				BackblazeS3::class,
				BackblazeS3\Protocol::class,
				array(
					'access_key' => 'key',
					'secret'     => 'secret',
					'bucket'     => 'my-bucket',
					'region'     => 'us-west-004',
				),
				'https://my-bucket.s3.us-west-004.backblazeb2.com/dir/example.jpg',
				'https://my-bucket.s3.us-west-004.backblazeb2.com/',
				'https://my-bucket.s3.us-west-004.backblazeb2.com/',
			),
			'Cloudflare R2'       => array(
				CloudflareR2::class,
				CloudflareR2\Protocol::class,
				array(
					'account_id'    => 'account123',
					'eu'            => 0,
					'access_key'    => 'key',
					'secret'        => 'secret',
					'bucket'        => 'my-bucket',
					'public_domain' => '',
				),
				'https://dash.cloudflare.com/account123/r2/buckets/my-bucket/objects/dir/example.jpg',
				'https://dash.cloudflare.com/account123/',
				'https://cdn.example.com/',
			),
			'DigitalOcean Spaces' => array(
				DigitalOceanSpaces::class,
				DigitalOceanSpaces\Protocol::class,
				array(
					'access_key' => 'key',
					'secret'     => 'secret',
					'bucket'     => 'my-bucket',
					'region'     => 'fra1',
				),
				'https://my-bucket.fra1.digitaloceanspaces.com/dir/example.jpg',
				'https://my-bucket.fra1.digitaloceanspaces.com/',
				'https://my-bucket.fra1.digitaloceanspaces.com/',
			),
		);
	}

	/**
	 * Return the responses for the S3 commands and set the HTTP responses to mark the given files as public or private.
	 *
	 * @param class-string<Platform_Base> $platform_class_name The class name of the platform.
	 * @param string                      $public_base_url     The public base URL of the bucket.
	 * @param array<int,string>           $keys                The keys of the files.
	 * @param bool                        $public              True if the files should be public available.
	 *
	 * @return array<string,mixed>
	 */
	private function set_availability( string $platform_class_name, string $public_base_url, array $keys, bool $public ): array {
		// AWS S3 checks the ACL first, and only if it is not public the public URL.
		if ( AwsS3::class === $platform_class_name ) {
			if ( ! $public ) {
				foreach ( $keys as $key ) {
					$this->add_http_response( $public_base_url . $key, 403 );
				}
			}
			return array( 'GetObjectAcl' => $this->get_acl_result( $public ) );
		}

		// Cloudflare R2 is only public with a public domain (which we only set for public files).
		if ( CloudflareR2::class === $platform_class_name && ! $public ) {
			return array();
		}

		// all others check the public URL.
		foreach ( $keys as $key ) {
			$this->add_http_response( $public_base_url . $key, $public ? 200 : 403 );
		}
		return array();
	}

	/**
	 * Return the response for the S3 command "GetObject", which saves the file like the S3 client does it.
	 *
	 * @param string $content_type The content type of the file.
	 *
	 * @return callable
	 */
	private function get_object_response( string $content_type = 'image/jpeg' ): callable {
		return function ( CommandInterface $command ) use ( $content_type ): array {
			// save the file like the S3 client does it with "SaveAs" (which the S3 client moves to the HTTP option "sink").
			$target  = isset( $command['@http']['sink'] ) ? (string) $command['@http']['sink'] : (string) $command['SaveAs'];
			$content = (string) file_get_contents( UNIT_TESTS_DATA_PLUGIN_DIR . 'example.jpg' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			file_put_contents( $target, $content ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			$this->tmp_files[] = $target;

			return array(
				'ContentType'   => $content_type,
				'ContentLength' => strlen( $content ),
				'LastModified'  => new DateTimeResult( '2024-01-02T03:04:05Z' ),
			);
		};
	}

	/**
	 * Return the protocol object with the given fields for the given URL.
	 *
	 * @param string              $protocol_class_name The class name of the protocol.
	 * @param string              $url                 The URL.
	 * @param array<string,mixed> $fields              The fields.
	 *
	 * @return \ExternalFilesFromAwsS3\Protocol_Base
	 */
	private function get_protocol( string $protocol_class_name, string $url, array $fields ): \ExternalFilesFromAwsS3\Protocol_Base {
		$protocol = new $protocol_class_name( $url );
		$protocol->set_fields( $fields );
		return $protocol;
	}

	/**
	 * Test the settings of the base object, which are used by all platforms.
	 *
	 * @return void
	 */
	public function test_base_settings(): void {
		$protocol = new \ExternalFilesFromAwsS3\Protocol_Base( 'https://example.com/example.jpg' );
		$this->assertTrue( $protocol->is_available() );
		$this->assertFalse( $protocol->is_url_compatible() );
		$this->assertTrue( $protocol->should_be_saved_local() );
		$this->assertFalse( $protocol->can_change_hosting() );
		$this->assertFalse( $protocol->can_check_availability() );
		$this->assertFalse( $protocol->is_url_reachable() );
		$this->assertTrue( $protocol->check_url( 'https://example.com/example.jpg' ) );
		$this->assertFalse( $protocol->check_url( '' ) );
		$this->assertEquals( 'https://example.com/my%20example%20file.jpg', $protocol->replace_spaces( 'https://example.com/my example file.jpg' ) );
		$this->assertEquals( 'https://example.com/example.jpg', $this->call_method( $protocol, 'get_key_of_file', array( 'https://example.com/example.jpg' ) ) );

		// the base object does not have a platform, so it does not return any files.
		$this->assertEquals( array(), $protocol->get_url_infos() );
	}

	/**
	 * Test the import of a single file, which is not public available.
	 *
	 * @dataProvider platforms_provider
	 *
	 * @param class-string<Platform_Base> $platform_class_name The class name of the platform.
	 * @param string                      $protocol_class_name The class name of the protocol.
	 * @param array<string,mixed>         $values              The values for the fields.
	 * @param string                      $file_url            The URL of the file.
	 * @param string                      $directory_url       The URL of the main directory.
	 * @param string                      $public_base_url     The public base URL of the bucket.
	 *
	 * @return void
	 */
	public function test_get_url_infos_for_private_file( string $platform_class_name, string $protocol_class_name, array $values, string $file_url, string $directory_url, string $public_base_url ): void {
		$responses              = $this->set_availability( $platform_class_name, $public_base_url, array( 'dir/example.jpg' ), false );
		$responses['GetObject'] = $this->get_object_response();
		$s3                     = $this->use_s3_mock( $platform_class_name, $responses );
		$fields                 = $this->set_platform_fields( $s3, $values );

		$results = $this->get_protocol( $protocol_class_name, $file_url, $fields )->get_url_infos();

		// check the result.
		$this->assertCount( 1, $results );
		$file = $results[0];
		$this->assertEquals( 'example.jpg', $file['title'] );
		$this->assertTrue( $file['local'] );
		$this->assertEquals( $file_url, $file['url'] );
		$this->assertEquals( 'image/jpeg', $file['mime-type'] );
		$this->assertEquals( filesize( UNIT_TESTS_DATA_PLUGIN_DIR . 'example.jpg' ), $file['filesize'] );
		$this->assertNotEmpty( $file['last-modified'] );
		$this->assertFileExists( $file['tmp-file'] );
		$this->assertStringEndsWith( '.jpg', $file['tmp-file'] );
		$this->assertFileEquals( UNIT_TESTS_DATA_PLUGIN_DIR . 'example.jpg', $file['tmp-file'] );

		// check the request for the file.
		$commands = $this->get_s3_commands();
		$this->assertEquals( 'GetObject', $commands[0]->getName() );
		$this->assertEquals( 'my-bucket', $commands[0]['Bucket'] );
		$this->assertEquals( 'dir/example.jpg', $commands[0]['Key'] );
	}

	/**
	 * Test the import of a single file, which is public available.
	 *
	 * @dataProvider platforms_provider
	 *
	 * @param class-string<Platform_Base> $platform_class_name The class name of the platform.
	 * @param string                      $protocol_class_name The class name of the protocol.
	 * @param array<string,mixed>         $values              The values for the fields.
	 * @param string                      $file_url            The URL of the file.
	 * @param string                      $directory_url       The URL of the main directory.
	 * @param string                      $public_base_url     The public base URL of the bucket.
	 *
	 * @return void
	 */
	public function test_get_url_infos_for_public_file( string $platform_class_name, string $protocol_class_name, array $values, string $file_url, string $directory_url, string $public_base_url ): void {
		// Cloudflare R2 files are only public via a custom domain.
		if ( CloudflareR2::class === $platform_class_name ) {
			$values['public_domain'] = 'cdn.example.com';
		}

		$responses              = $this->set_availability( $platform_class_name, $public_base_url, array( 'dir/example.jpg' ), true );
		$responses['GetObject'] = $this->get_object_response();
		$s3                     = $this->use_s3_mock( $platform_class_name, $responses );
		$fields                 = $this->set_platform_fields( $s3, $values );

		$results = $this->get_protocol( $protocol_class_name, $file_url, $fields )->get_url_infos();

		$this->assertCount( 1, $results );
		$this->assertFalse( $results[0]['local'] );
		$this->assertEquals( $public_base_url . 'dir/example.jpg', $results[0]['url'] );
		$this->assertEquals( 'example.jpg', $results[0]['title'] );
	}

	/**
	 * Test if the mime type is detected by the file name, if the platform returns a generic content type.
	 *
	 * @dataProvider platforms_provider
	 *
	 * @param class-string<Platform_Base> $platform_class_name The class name of the platform.
	 * @param string                      $protocol_class_name The class name of the protocol.
	 * @param array<string,mixed>         $values              The values for the fields.
	 * @param string                      $file_url            The URL of the file.
	 * @param string                      $directory_url       The URL of the main directory.
	 * @param string                      $public_base_url     The public base URL of the bucket.
	 *
	 * @return void
	 */
	public function test_get_url_infos_with_generic_content_type( string $platform_class_name, string $protocol_class_name, array $values, string $file_url, string $directory_url, string $public_base_url ): void {
		foreach ( array( '', 'application/octet-stream', 'binary/octet-stream' ) as $content_type ) {
			$responses              = $this->set_availability( $platform_class_name, $public_base_url, array( 'dir/example.jpg' ), false );
			$responses['GetObject'] = $this->get_object_response( $content_type );
			$s3                     = $this->use_s3_mock( $platform_class_name, $responses );
			$fields                 = $this->set_platform_fields( $s3, $values );

			$results = $this->get_protocol( $protocol_class_name, $file_url, $fields )->get_url_infos();
			$this->assertEquals( 'image/jpeg', $results[0]['mime-type'], 'Wrong mime type for content type "' . $content_type . '".' );
		}
	}

	/**
	 * Test if a file, which already exists in the media library, is not imported again.
	 *
	 * @dataProvider platforms_provider
	 *
	 * @param class-string<Platform_Base> $platform_class_name The class name of the platform.
	 * @param string                      $protocol_class_name The class name of the protocol.
	 * @param array<string,mixed>         $values              The values for the fields.
	 * @param string                      $file_url            The URL of the file.
	 * @param string                      $directory_url       The URL of the main directory.
	 * @param string                      $public_base_url     The public base URL of the bucket.
	 *
	 * @return void
	 */
	public function test_get_url_infos_for_duplicate( string $platform_class_name, string $protocol_class_name, array $values, string $file_url, string $directory_url, string $public_base_url ): void {
		// create the existing file.
		$attachment_id = self::factory()->attachment->create( array( 'post_mime_type' => 'image/jpeg' ) );
		update_post_meta( $attachment_id, EFML_POST_META_URL, $file_url );

		$responses              = $this->set_availability( $platform_class_name, $public_base_url, array( 'dir/example.jpg' ), false );
		$responses['GetObject'] = $this->get_object_response();
		$s3                     = $this->use_s3_mock( $platform_class_name, $responses );
		$fields                 = $this->set_platform_fields( $s3, $values );

		$results = $this->get_protocol( $protocol_class_name, $file_url, $fields )->get_url_infos();

		$this->assertEquals( array(), $results );

		// the temporary file must be deleted.
		$this->assertNotEmpty( $this->tmp_files );
		$this->assertFileDoesNotExist( $this->tmp_files[0] );

		// the duplicate must be logged.
		$log = $this->get_log_entries( $file_url );
		$this->assertNotEmpty( $log );
		$this->assertStringContainsString( 'already exist', $log[0]['log'] );
	}

	/**
	 * Test the import of a single file, if the platform returns an error.
	 *
	 * @dataProvider platforms_provider
	 *
	 * @param class-string<Platform_Base> $platform_class_name The class name of the platform.
	 * @param string                      $protocol_class_name The class name of the protocol.
	 * @param array<string,mixed>         $values              The values for the fields.
	 * @param string                      $file_url            The URL of the file.
	 *
	 * @return void
	 */
	public function test_get_url_infos_with_error( string $platform_class_name, string $protocol_class_name, array $values, string $file_url ): void {
		$s3     = $this->use_s3_mock( $platform_class_name, array( 'GetObject' => $this->get_s3_exception( 'GetObject', 404, 'NoSuchKey' ) ) );
		$fields = $this->set_platform_fields( $s3, $values );

		$results = $this->get_protocol( $protocol_class_name, $file_url, $fields )->get_url_infos();

		$this->assertEquals( array(), $results );

		// the error must be logged.
		$log = $this->get_log_entries( $file_url );
		$this->assertNotEmpty( $log );
		$this->assertEquals( 'error', $log[0]['state'] );
		$this->assertStringContainsString( 'NoSuchKey', $log[0]['log'] );
	}

	/**
	 * Test the import of a complete directory.
	 *
	 * @dataProvider platforms_provider
	 *
	 * @param class-string<Platform_Base> $platform_class_name The class name of the platform.
	 * @param string                      $protocol_class_name The class name of the protocol.
	 * @param array<string,mixed>         $values              The values for the fields.
	 * @param string                      $file_url            The URL of the file.
	 * @param string                      $directory_url       The URL of the main directory.
	 * @param string                      $public_base_url     The public base URL of the bucket.
	 *
	 * @return void
	 */
	public function test_get_url_infos_for_directory( string $platform_class_name, string $protocol_class_name, array $values, string $file_url, string $directory_url, string $public_base_url ): void {
		$date                       = new DateTimeResult( '2024-01-02T03:04:05Z' );
		$responses                  = $this->set_availability( $platform_class_name, $public_base_url, array( 'example.jpg', 'dir/example.jpg' ), false );
		$responses['GetObject']     = $this->get_object_response();
		$responses['ListObjectsV2'] = array(
			'Contents' => array(
				array( 'Key' => 'example.jpg', 'Size' => 706, 'LastModified' => $date ),
				array( 'Key' => 'dir/example.jpg', 'Size' => 706, 'LastModified' => $date ),
			),
		);
		$s3                         = $this->use_s3_mock( $platform_class_name, $responses );
		$fields                     = $this->set_platform_fields( $s3, $values );

		// collect the files, which are announced via hook.
		$announced = array();
		$callback  = static function ( string $url, array $files ) use ( &$announced ): void {
			$announced = $files;
		};
		add_action( 'efmlawss3_directory_import_files', $callback, 10, 2 );
		$results = $this->get_protocol( $protocol_class_name, $directory_url, $fields )->get_url_infos();
		remove_action( 'efmlawss3_directory_import_files', $callback );

		// check the result.
		$this->assertCount( 2, $results );
		$this->assertNotEmpty( $announced );
		foreach ( $results as $file ) {
			$this->assertEquals( 'example.jpg', $file['title'] );
			$this->assertTrue( $file['local'] );
			$this->assertEquals( 'image/jpeg', $file['mime-type'] );
			$this->assertEquals( 706, $file['filesize'] );
			$this->assertFileExists( $file['tmp-file'] );
		}

		// check the requests: the list of files and each file.
		$keys = array();
		foreach ( $this->get_s3_commands() as $command ) {
			if ( 'GetObject' === $command->getName() ) {
				$keys[] = $command['Key'];
			}
		}
		sort( $keys );
		$this->assertEquals( array( 'dir/example.jpg', 'example.jpg' ), $keys );
	}
}
