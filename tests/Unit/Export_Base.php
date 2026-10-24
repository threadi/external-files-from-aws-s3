<?php
/**
 * Tests for class ExternalFilesFromAwsS3\Export_Base and the export objects of each platform.
 *
 * @package external-files-from-aws-s3
 */

namespace ExternalFilesFromAwsS3\Tests\Unit;

use Aws\CommandInterface;
use ExternalFilesFromAwsS3\Platform_Base;
use ExternalFilesFromAwsS3\Platforms\AwsS3;
use ExternalFilesFromAwsS3\Platforms\BackblazeS3;
use ExternalFilesFromAwsS3\Platforms\CloudflareR2;
use ExternalFilesFromAwsS3\Platforms\DigitalOceanSpaces;
use ExternalFilesFromAwsS3\Tests\externalFilesFromAwsS3Tests;

/**
 * Object to test functions in class ExternalFilesFromAwsS3\Export_Base.
 */
class Export_Base extends externalFilesFromAwsS3Tests {
	/**
	 * Return the configuration of each platform for the tests.
	 *
	 * Each entry contains:
	 * - the class names of the platform and its export object.
	 * - the values for the fields.
	 * - the directory (aka target) for the export.
	 * - the public base URL of the bucket.
	 *
	 * @return array<string,array<int,mixed>>
	 */
	public function platforms_provider(): array {
		return array(
			'AWS S3'              => array(
				AwsS3::class,
				AwsS3\Export::class,
				array(
					'access_key' => 'key',
					'secret'     => 'secret',
					'bucket'     => 'my-bucket',
					'region'     => 'eu-central-1',
				),
				'https://console.aws.amazon.com/s3/buckets/my-bucket/',
				'https://my-bucket.s3.eu-central-1.amazonaws.com/',
			),
			'Backblaze S3'        => array(
				BackblazeS3::class,
				BackblazeS3\Export::class,
				array(
					'access_key' => 'key',
					'secret'     => 'secret',
					'bucket'     => 'my-bucket',
					'region'     => 'us-west-004',
				),
				'https://my-bucket.s3.us-west-004.backblazeb2.com/',
				'https://my-bucket.s3.us-west-004.backblazeb2.com/',
			),
			'Cloudflare R2'       => array(
				CloudflareR2::class,
				CloudflareR2\Export::class,
				array(
					'account_id'    => 'account123',
					'eu'            => 0,
					'access_key'    => 'key',
					'secret'        => 'secret',
					'bucket'        => 'my-bucket',
					'public_domain' => 'cdn.example.com',
				),
				'https://dash.cloudflare.com/account123/',
				'https://cdn.example.com/',
			),
			'DigitalOcean Spaces' => array(
				DigitalOceanSpaces::class,
				DigitalOceanSpaces\Export::class,
				array(
					'access_key' => 'key',
					'secret'     => 'secret',
					'bucket'     => 'my-bucket',
					'region'     => 'fra1',
				),
				'https://my-bucket.fra1.digitaloceanspaces.com/',
				'https://my-bucket.fra1.digitaloceanspaces.com/',
			),
		);
	}

	/**
	 * Return the responses for the S3 commands and set the HTTP responses to mark the given file as public or private.
	 *
	 * @param class-string<Platform_Base> $platform_class_name The class name of the platform.
	 * @param string                      $public_url          The public URL of the file.
	 * @param bool                        $public              True if the file should be public available.
	 *
	 * @return array<string,mixed>
	 */
	private function set_availability( string $platform_class_name, string $public_url, bool $public ): array {
		// AWS S3 checks the ACL first, and only if it is not public the public URL.
		if ( AwsS3::class === $platform_class_name ) {
			if ( ! $public ) {
				$this->add_http_response( $public_url, 403 );
			}
			return array( 'GetObjectAcl' => $this->get_acl_result( $public ) );
		}

		// all others check the public URL.
		$this->add_http_response( $public_url, $public ? 200 : 403 );
		return array();
	}

	/**
	 * Return the credentials like the main plugin uses them for the export.
	 *
	 * @param array<string,mixed> $fields    The fields.
	 * @param string              $directory The directory.
	 *
	 * @return array<string,mixed>
	 */
	private function get_credentials( array $fields, string $directory ): array {
		return array(
			'fields'    => $fields,
			'directory' => $directory,
		);
	}

	/**
	 * Test if each export object is a singleton and uses its platform.
	 *
	 * @dataProvider platforms_provider
	 *
	 * @param class-string<Platform_Base> $platform_class_name The class name of the platform.
	 * @param string                      $export_class_name   The class name of the export object.
	 *
	 * @return void
	 */
	public function test_get_instance( string $platform_class_name, string $export_class_name ): void {
		$export = $export_class_name::get_instance();
		$this->assertInstanceOf( \ExternalFilesFromAwsS3\Export_Base::class, $export );
		$this->assertInstanceOf( \ExternalFilesInMediaLibrary\ExternalFiles\Export_Base::class, $export );
		$this->assertSame( $export, $export_class_name::get_instance() );
		$this->assertSame( $export, $platform_class_name::get_instance()->get_export_object() );
		$this->assertSame( $platform_class_name::get_instance(), $this->call_method( $export, 'get_directory_listing_object' ) );
	}

	/**
	 * Test the export of a file into a directory of the bucket.
	 *
	 * @dataProvider platforms_provider
	 *
	 * @param class-string<Platform_Base> $platform_class_name The class name of the platform.
	 * @param string                      $export_class_name   The class name of the export object.
	 * @param array<string,mixed>         $values              The values for the fields.
	 * @param string                      $directory           The directory for the export.
	 * @param string                      $public_base_url     The public base URL of the bucket.
	 *
	 * @return void
	 */
	public function test_export_file( string $platform_class_name, string $export_class_name, array $values, string $directory, string $public_base_url ): void {
		$attachment_id = $this->create_local_attachment();
		$file_path     = (string) get_attached_file( $attachment_id, true );
		$key           = 'uploads/' . basename( $file_path );

		$responses              = $this->set_availability( $platform_class_name, $public_base_url . $key, true );
		$responses['PutObject'] = array( 'ObjectURL' => $public_base_url . $key );
		$s3                     = $this->use_s3_mock( $platform_class_name, $responses );
		$fields                 = $this->set_platform_fields( $s3, $values );

		$url = $export_class_name::get_instance()->export_file( $attachment_id, $directory, $this->get_credentials( $fields, $directory . 'uploads/' ) );

		// check the result.
		$this->assertEquals( $public_base_url . $key, $url );
		$this->assertEquals( $key, get_post_meta( $attachment_id, 'efml_s3_key', true ) );

		// check the upload.
		$uploads = array_values(
			array_filter(
				$this->get_s3_commands(),
				static function ( CommandInterface $command ): bool {
					return 'PutObject' === $command->getName();
				}
			)
		);
		$this->assertCount( 1, $uploads );
		$this->assertEquals( 'my-bucket', $uploads[0]['Bucket'] );
		$this->assertEquals( $key, $uploads[0]['Key'] );
		$this->assertEquals( file_get_contents( $file_path ), (string) $uploads[0]['Body'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}

	/**
	 * Test the export of a file, which would not be public available after the export.
	 *
	 * @dataProvider platforms_provider
	 *
	 * @param class-string<Platform_Base> $platform_class_name The class name of the platform.
	 * @param string                      $export_class_name   The class name of the export object.
	 * @param array<string,mixed>         $values              The values for the fields.
	 * @param string                      $directory           The directory for the export.
	 * @param string                      $public_base_url     The public base URL of the bucket.
	 *
	 * @return void
	 */
	public function test_export_file_without_public_access( string $platform_class_name, string $export_class_name, array $values, string $directory, string $public_base_url ): void {
		$attachment_id = $this->create_local_attachment();
		$key           = basename( (string) get_attached_file( $attachment_id, true ) );

		$responses              = $this->set_availability( $platform_class_name, $public_base_url . $key, false );
		$responses['PutObject'] = array();
		$s3                     = $this->use_s3_mock( $platform_class_name, $responses );
		$fields                 = $this->set_platform_fields( $s3, $values );

		$this->assertFalse( $export_class_name::get_instance()->export_file( $attachment_id, $directory, $this->get_credentials( $fields, $directory ) ) );
		$this->assertEmpty( get_post_meta( $attachment_id, 'efml_s3_key', true ) );

		// the problem must be logged.
		$log = $this->get_log_entries( $directory );
		$this->assertNotEmpty( $log );
		$this->assertStringContainsString( 'would not be public available', $log[0]['log'] );
	}

	/**
	 * Test the export of a file to a target, which is not a URL of this platform.
	 *
	 * @dataProvider platforms_provider
	 *
	 * @param class-string<Platform_Base> $platform_class_name The class name of the platform.
	 * @param string                      $export_class_name   The class name of the export object.
	 * @param array<string,mixed>         $values              The values for the fields.
	 *
	 * @return void
	 */
	public function test_export_file_with_wrong_target( string $platform_class_name, string $export_class_name, array $values ): void {
		$attachment_id = $this->create_local_attachment();
		$s3            = $this->use_s3_mock( $platform_class_name, array() );
		$fields        = $this->set_platform_fields( $s3, $values );

		$this->assertFalse( $export_class_name::get_instance()->export_file( $attachment_id, 'https://example.com/', $this->get_credentials( $fields, 'https://example.com/' ) ) );
		$this->assertEmpty( $this->get_s3_commands() );

		// the problem must be logged.
		$log = $this->get_log_entries( 'https://example.com/' );
		$this->assertNotEmpty( $log );
		$this->assertStringContainsString( 'not a S3-URL', $log[0]['log'] );
	}

	/**
	 * Test the export of an attachment without file.
	 *
	 * @dataProvider platforms_provider
	 *
	 * @param class-string<Platform_Base> $platform_class_name The class name of the platform.
	 * @param string                      $export_class_name   The class name of the export object.
	 * @param array<string,mixed>         $values              The values for the fields.
	 * @param string                      $directory           The directory for the export.
	 *
	 * @return void
	 */
	public function test_export_file_without_file( string $platform_class_name, string $export_class_name, array $values, string $directory ): void {
		$s3     = $this->use_s3_mock( $platform_class_name, array() );
		$fields = $this->set_platform_fields( $s3, $values );

		// test 1: attachment without file.
		$attachment_id = self::factory()->attachment->create( array( 'post_mime_type' => 'image/jpeg' ) );
		$this->assertFalse( $export_class_name::get_instance()->export_file( $attachment_id, $directory, $this->get_credentials( $fields, $directory ) ) );

		// test 2: attachment with a not existing file.
		update_attached_file( $attachment_id, '/not/existing/example.jpg' );
		$this->assertFalse( $export_class_name::get_instance()->export_file( $attachment_id, $directory, $this->get_credentials( $fields, $directory ) ) );

		$this->assertEmpty( $this->get_s3_commands() );
	}

	/**
	 * Test the deletion of an exported file.
	 *
	 * @dataProvider platforms_provider
	 *
	 * @param class-string<Platform_Base> $platform_class_name The class name of the platform.
	 * @param string                      $export_class_name   The class name of the export object.
	 * @param array<string,mixed>         $values              The values for the fields.
	 * @param string                      $directory           The directory for the export.
	 *
	 * @return void
	 */
	public function test_delete_exported_file( string $platform_class_name, string $export_class_name, array $values, string $directory ): void {
		$attachment_id = self::factory()->attachment->create( array( 'post_mime_type' => 'image/jpeg' ) );
		update_post_meta( $attachment_id, 'efml_s3_key', 'uploads/example.jpg' );

		$s3     = $this->use_s3_mock( $platform_class_name, array( 'DeleteObject' => array() ) );
		$fields = $this->set_platform_fields( $s3, $values );

		$this->assertTrue( $export_class_name::get_instance()->delete_exported_file( $directory . 'uploads/example.jpg', $this->get_credentials( $fields, $directory ), $attachment_id ) );

		$commands = $this->get_s3_commands();
		$this->assertCount( 1, $commands );
		$this->assertEquals( 'DeleteObject', $commands[0]->getName() );
		$this->assertEquals( 'my-bucket', $commands[0]['Bucket'] );
		$this->assertEquals( 'uploads/example.jpg', $commands[0]['Key'] );
	}

	/**
	 * Test the deletion of an exported file, which could not be deleted.
	 *
	 * @dataProvider platforms_provider
	 *
	 * @param class-string<Platform_Base> $platform_class_name The class name of the platform.
	 * @param string                      $export_class_name   The class name of the export object.
	 * @param array<string,mixed>         $values              The values for the fields.
	 * @param string                      $directory           The directory for the export.
	 *
	 * @return void
	 */
	public function test_delete_exported_file_with_error( string $platform_class_name, string $export_class_name, array $values, string $directory ): void {
		$attachment_id = self::factory()->attachment->create( array( 'post_mime_type' => 'image/jpeg' ) );
		update_post_meta( $attachment_id, 'efml_s3_key', 'uploads/example.jpg' );

		$s3     = $this->use_s3_mock( $platform_class_name, array( 'DeleteObject' => $this->get_s3_exception( 'DeleteObject', 403 ) ) );
		$fields = $this->set_platform_fields( $s3, $values );

		$this->assertFalse( $export_class_name::get_instance()->delete_exported_file( $directory . 'uploads/example.jpg', $this->get_credentials( $fields, $directory ), $attachment_id ) );

		// the error must be logged.
		$log = $this->get_log_entries();
		$this->assertNotEmpty( $log );
		$this->assertStringContainsString( 'could not be deleted', $log[0]['log'] );
	}

	/**
	 * Test the deletion of files, which are not exported to this platform.
	 *
	 * @dataProvider platforms_provider
	 *
	 * @param class-string<Platform_Base> $platform_class_name The class name of the platform.
	 * @param string                      $export_class_name   The class name of the export object.
	 * @param array<string,mixed>         $values              The values for the fields.
	 * @param string                      $directory           The directory for the export.
	 *
	 * @return void
	 */
	public function test_delete_exported_file_without_export( string $platform_class_name, string $export_class_name, array $values, string $directory ): void {
		$attachment_id = self::factory()->attachment->create( array( 'post_mime_type' => 'image/jpeg' ) );
		$s3            = $this->use_s3_mock( $platform_class_name, array() );
		$fields        = $this->set_platform_fields( $s3, $values );
		$export        = $export_class_name::get_instance();

		// test 1: the URL is not from this platform.
		update_post_meta( $attachment_id, 'efml_s3_key', 'uploads/example.jpg' );
		$this->assertFalse( $export->delete_exported_file( 'https://example.com/uploads/example.jpg', $this->get_credentials( $fields, $directory ), $attachment_id ) );

		// test 2: the file does not have a key (it was not exported).
		delete_post_meta( $attachment_id, 'efml_s3_key' );
		$this->assertFalse( $export->delete_exported_file( $directory . 'uploads/example.jpg', $this->get_credentials( $fields, $directory ), $attachment_id ) );

		$this->assertEmpty( $this->get_s3_commands() );
	}

	/**
	 * Test the base object, which does not have a platform.
	 *
	 * @return void
	 */
	public function test_base_object_without_platform(): void {
		$export = $this->getMockBuilder( \ExternalFilesFromAwsS3\Export_Base::class )->onlyMethods( array() )->getMock();
		$this->assertFalse( $export->export_file( 1, 'https://example.com/', array() ) );
		$this->assertFalse( $export->delete_exported_file( 'https://example.com/', array(), 1 ) );
	}
}
