<?php
/**
 * Tests for the support of private files in class ExternalFilesFromAwsS3\Protocol_Base.
 *
 * @package external-files-from-aws-s3
 */

namespace ExternalFilesFromAwsS3\Tests\Unit;

use Aws\CommandInterface;
use ExternalFilesFromAwsS3\Platforms\AwsS3;
use ExternalFilesFromAwsS3\Platforms\S3Compatible;
use ExternalFilesFromAwsS3\Tests\externalFilesFromAwsS3Tests;

/**
 * Object to test the support of private files.
 */
class PrivateFiles extends externalFilesFromAwsS3Tests {
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
	 * Return the protocol object for the given URL with configured fields.
	 *
	 * @param string $url The URL.
	 *
	 * @return AwsS3\Protocol
	 */
	private function get_protocol( string $url ): AwsS3\Protocol {
		$fields   = $this->set_platform_fields( AwsS3::get_instance(), $this->field_values );
		$protocol = new AwsS3\Protocol( $url );
		$protocol->set_fields( $fields );
		return $protocol;
	}

	/**
	 * Create an external file for the given URL, which is saved privately.
	 *
	 * @param string $url The URL.
	 * @param string $key The key in the bucket.
	 *
	 * @return int
	 */
	private function create_private_file( string $url, string $key ): int {
		$attachment_id = $this->create_local_attachment();
		update_post_meta( $attachment_id, EFML_POST_META_URL, $url );
		update_post_meta( $attachment_id, 'efml_private_file', 1 );
		update_post_meta( $attachment_id, 'efml_s3_key', $key );
		return $attachment_id;
	}

	/**
	 * Test the pre-signed URL for a private file.
	 *
	 * @return void
	 */
	public function test_get_signed_url(): void {
		$url      = 'https://my-bucket.s3.eu-central-1.amazonaws.com/uploads/my%20file.pdf';
		$protocol = $this->get_protocol( $url );
		$start    = 1767225600;
		$expires  = $start + 7200;

		// get the signed URL.
		$signed_url = $protocol->get_signed_url( $url, $start, $expires );
		$this->assertStringStartsWith( 'https://my-bucket.s3.eu-central-1.amazonaws.com/uploads/my%20file.pdf?', $signed_url );
		$this->assertStringContainsString( 'X-Amz-Signature=', $signed_url );
		$this->assertStringContainsString( 'X-Amz-Expires=7200', $signed_url );
		$this->assertStringContainsString( 'X-Amz-Date=20260101T000000Z', $signed_url );
		$this->assertStringContainsString( 'AKIAEXAMPLE', $signed_url );

		// the same time range results in the same URL.
		$this->assertEquals( $signed_url, $protocol->get_signed_url( $url, $start, $expires ) );

		// another time range results in another URL.
		$this->assertNotEquals( $signed_url, $protocol->get_signed_url( $url, $start + 3600, $expires + 3600 ) );

		// invalid values.
		$this->assertEquals( '', $protocol->get_signed_url( '', $start, $expires ) );
		$this->assertEquals( '', $protocol->get_signed_url( $url, $expires, $start ) );
	}

	/**
	 * Test that the saved key of an exported file is used for the pre-signed URL.
	 *
	 * @return void
	 */
	public function test_get_signed_url_uses_saved_key(): void {
		$url = 'https://my-bucket.s3.eu-central-1.amazonaws.com/uploads/example.jpg';
		$this->create_private_file( $url, 'other/folder/example.jpg' );

		// get the signed URL.
		$signed_url = $this->get_protocol( $url )->get_signed_url( $url, time(), time() + 600 );
		$this->assertStringStartsWith( 'https://my-bucket.s3.eu-central-1.amazonaws.com/other/folder/example.jpg?', $signed_url );
	}

	/**
	 * Test the pre-signed URL for an S3 compatible storage.
	 *
	 * @return void
	 */
	public function test_get_signed_url_for_s3_compatible(): void {
		$fields   = $this->set_platform_fields(
			S3Compatible::get_instance(),
			array(
				'endpoint'   => 'https://s3.example.com',
				'access_key' => 'key',
				'secret'     => 'secret',
				'bucket'     => 'my-bucket',
				'region'     => 'us-east-1',
				'path_style' => 1,
			)
		);
		$url      = 'https://s3.example.com/my-bucket/uploads/example.jpg';
		$protocol = new S3Compatible\Protocol( $url );
		$protocol->set_fields( $fields );

		// get the signed URL.
		$signed_url = $protocol->get_signed_url( $url, time(), time() + 600 );
		$this->assertStringStartsWith( 'https://s3.example.com/my-bucket/uploads/example.jpg?', $signed_url );
		$this->assertStringContainsString( 'X-Amz-Signature=', $signed_url );
	}

	/**
	 * Test the temporary file of a private file, which is loaded via the S3 client.
	 *
	 * @return void
	 */
	public function test_get_temp_file_of_private_file(): void {
		$url = 'https://my-bucket.s3.eu-central-1.amazonaws.com/uploads/example.jpg';
		$this->create_private_file( $url, 'uploads/example.jpg' );

		// mock the S3 client.
		$s3 = $this->use_s3_mock( AwsS3::class, array( 'GetObject' => array() ) );
		$this->set_platform_fields( $s3, $this->field_values );
		$protocol = new AwsS3\Protocol( $url );
		$protocol->set_fields( $s3->get_fields() );

		// get the temporary file.
		$tmp_file = $protocol->get_temp_file( $url, \ExternalFilesInMediaLibrary\Plugin\Helper::get_wp_filesystem() );
		$this->assertIsString( $tmp_file );

		// the file has been requested via the S3 client.
		$commands = array_values(
			array_filter(
				$this->get_s3_commands(),
				static function ( CommandInterface $command ): bool {
					return 'GetObject' === $command->getName();
				}
			)
		);
		$this->assertCount( 1, $commands );
		$this->assertEquals( 'my-bucket', $commands[0]['Bucket'] );
		$this->assertEquals( 'uploads/example.jpg', $commands[0]['Key'] );

		// cleanup.
		wp_delete_file( $tmp_file );
	}

	/**
	 * Test the URLs of the platforms.
	 *
	 * @return void
	 */
	public function test_get_platform_url(): void {
		$this->assertEquals( 'https://console.aws.amazon.com/s3/buckets/', AwsS3::get_instance()->get_platform_url() );
		$this->assertEquals( 'https://dash.cloudflare.com/?to=/:account/r2/overview', \ExternalFilesFromAwsS3\Platforms\CloudflareR2::get_instance()->get_platform_url() );
		$this->assertEquals( 'https://secure.backblaze.com/b2_buckets.htm', \ExternalFilesFromAwsS3\Platforms\BackblazeS3::get_instance()->get_platform_url() );
		$this->assertEquals( 'https://cloud.digitalocean.com/spaces', \ExternalFilesFromAwsS3\Platforms\DigitalOceanSpaces::get_instance()->get_platform_url() );
		$this->assertEquals( 'https://console.hetzner.com/', \ExternalFilesFromAwsS3\Platforms\HetznerObjectStorage::get_instance()->get_platform_url() );
		$this->assertEquals( 'https://console.wasabisys.com/', \ExternalFilesFromAwsS3\Platforms\Wasabi::get_instance()->get_platform_url() );
		$this->assertEquals( 'https://dcd.ionos.com/', \ExternalFilesFromAwsS3\Platforms\IonosObjectStorage::get_instance()->get_platform_url() );
		$this->assertEquals( 'https://manager.eu.ovhcloud.com/', \ExternalFilesFromAwsS3\Platforms\OvhcloudObjectStorage::get_instance()->get_platform_url() );
		$this->assertEquals( 'https://console.scaleway.com/object-storage/buckets', \ExternalFilesFromAwsS3\Platforms\ScalewayObjectStorage::get_instance()->get_platform_url() );

		// the S3-compatible storage with own endpoint has no platform.
		$this->assertEquals( '', S3Compatible::get_instance()->get_platform_url() );
	}

	/**
	 * Test that the directory listing of each platform with a known web interface links to it.
	 *
	 * @return void
	 */
	public function test_global_actions_link_the_platform(): void {
		$platforms = array(
			AwsS3::get_instance(),
			\ExternalFilesFromAwsS3\Platforms\CloudflareR2::get_instance(),
			\ExternalFilesFromAwsS3\Platforms\BackblazeS3::get_instance(),
			\ExternalFilesFromAwsS3\Platforms\DigitalOceanSpaces::get_instance(),
			\ExternalFilesFromAwsS3\Platforms\HetznerObjectStorage::get_instance(),
			\ExternalFilesFromAwsS3\Platforms\IonosObjectStorage::get_instance(),
			\ExternalFilesFromAwsS3\Platforms\OvhcloudObjectStorage::get_instance(),
			\ExternalFilesFromAwsS3\Platforms\ScalewayObjectStorage::get_instance(),
			\ExternalFilesFromAwsS3\Platforms\Wasabi::get_instance(),
		);
		foreach ( $platforms as $platform ) {
			$actions = $this->call_method( $platform, 'get_global_actions' );
			$labels  = array_column( $actions, 'label' );
			$label   = 'Go to ' . $platform->get_label();
			$this->assertContains( $label, $labels, $platform->get_name() );
			$this->assertStringContainsString( esc_url( $platform->get_platform_url() ), $actions[ array_search( $label, $labels, true ) ]['action'] );

			// only one link to the platform.
			$this->assertCount( 1, array_filter( $actions, static fn( array $action ) => str_contains( $action['action'], 'location.href' ) && str_contains( $action['action'], esc_url( $platform->get_platform_url() ) ) ), $platform->get_name() );
		}

		// the S3-compatible storage with own endpoint has no link.
		$actions = $this->call_method( S3Compatible::get_instance(), 'get_global_actions' );
		foreach ( $actions as $action ) {
			$this->assertStringNotContainsString( 'Go to', $action['label'] );
		}
	}
}
