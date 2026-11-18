<?php
/**
 * Tests for class ExternalFilesFromAwsS3\Platforms\S3Compatible\Protocol.
 *
 * @package external-files-from-aws-s3
 */

namespace ExternalFilesFromAwsS3\Tests\Unit\Platforms\S3Compatible;

use ExternalFilesFromAwsS3\Platforms\HetznerObjectStorage;
use ExternalFilesFromAwsS3\Tests\externalFilesFromAwsS3Tests;
use ExternalFilesInMediaLibrary\ExternalFiles\Protocols;

/**
 * Object to test functions in class ExternalFilesFromAwsS3\Platforms\S3Compatible\Protocol.
 */
class Protocol extends externalFilesFromAwsS3Tests {
	/**
	 * The values for the fields we use in the tests.
	 *
	 * @var array<string,mixed>
	 */
	private array $field_values = array(
		'endpoint'   => 'https://s3.example.com',
		'region'     => 'us-east-1',
		'access_key' => 'key',
		'secret'     => 'secret',
		'bucket'     => 'my-bucket',
		'path_style' => 1,
		'public_url' => 'https://cdn.example.com',
	);

	/**
	 * Return the protocol object for the given URL with the fields of the actual test.
	 *
	 * @param string              $url    The URL.
	 * @param array<string,mixed> $values The values for the fields.
	 *
	 * @return \ExternalFilesFromAwsS3\Platforms\S3Compatible\Protocol
	 */
	private function get_protocol( string $url, array $values = array() ): \ExternalFilesFromAwsS3\Platforms\S3Compatible\Protocol {
		$fields   = $this->set_platform_fields( \ExternalFilesFromAwsS3\Platforms\S3Compatible::get_instance(), array_merge( $this->field_values, $values ) );
		$protocol = new \ExternalFilesFromAwsS3\Platforms\S3Compatible\Protocol( $url );
		$protocol->set_fields( $fields );
		\ExternalFilesFromAwsS3\Platforms\S3Compatible::get_instance()->set_fields( array() );
		return $protocol;
	}

	/**
	 * Test the name and the title.
	 *
	 * @return void
	 */
	public function test_get_name_and_title(): void {
		$protocol = $this->get_protocol( 'https://s3.example.com/my-bucket/example.jpg' );
		$this->assertEquals( 's3-compatible', $protocol->get_name() );
		$this->assertEquals( 'S3-compatible storage', $protocol->get_title() );
		$this->assertSame( \ExternalFilesFromAwsS3\Platforms\S3Compatible::get_instance(), $this->call_method( $protocol, 'get_directory_listing_object' ) );
	}

	/**
	 * Test the settings of the protocol.
	 *
	 * @return void
	 */
	public function test_settings(): void {
		$protocol = $this->get_protocol( 'https://s3.example.com/my-bucket/example.jpg' );
		$this->assertTrue( $protocol->is_available() );
		$this->assertFalse( $protocol->can_check_availability() );
		$this->assertFalse( $protocol->is_url_reachable() );
		$this->assertFalse( $protocol->can_change_hosting() );
	}

	/**
	 * Test which URLs are compatible: the URL of the bucket and the public URL from the fields.
	 *
	 * @return void
	 */
	public function test_is_url_compatible(): void {
		$this->assertTrue( $this->get_protocol( 'https://s3.example.com/my-bucket/dir/example.jpg' )->is_url_compatible() );
		$this->assertTrue( $this->get_protocol( 'https://cdn.example.com/dir/example.jpg' )->is_url_compatible() );
		$this->assertTrue( $this->get_protocol( 'https://my-bucket.s3.example.com/dir/example.jpg', array( 'path_style' => 0 ) )->is_url_compatible() );
		$this->assertFalse( $this->get_protocol( 'https://s3.example.com/other-bucket/example.jpg' )->is_url_compatible() );
		$this->assertFalse( $this->get_protocol( 'https://example.com/example.jpg' )->is_url_compatible() );

		// without fields the URL is not compatible.
		$protocol = new \ExternalFilesFromAwsS3\Platforms\S3Compatible\Protocol( 'https://s3.example.com/my-bucket/example.jpg' );
		$this->assertFalse( $protocol->is_url_compatible() );

		// the fields of the platform (e.g. from the global settings) are used, too.
		$this->set_platform_fields( \ExternalFilesFromAwsS3\Platforms\S3Compatible::get_instance(), $this->field_values );
		$this->assertTrue( $protocol->is_url_compatible() );
	}

	/**
	 * Test if the key of a file is extracted from its URL.
	 *
	 * @return void
	 */
	public function test_get_key_of_file(): void {
		$protocol = $this->get_protocol( 'https://s3.example.com/my-bucket/dir/example.jpg' );
		$this->assertEquals( 'dir/example.jpg', $this->call_method( $protocol, 'get_key_of_file', array( 'https://s3.example.com/my-bucket/dir/example.jpg' ) ) );
		$this->assertEquals( 'dir/example.jpg', $this->call_method( $protocol, 'get_key_of_file', array( 'https://cdn.example.com/dir/example.jpg' ) ) );
		$this->assertEquals( 'https://example.com/dir/example.jpg', $this->call_method( $protocol, 'get_key_of_file', array( 'https://example.com/dir/example.jpg' ) ) );
	}

	/**
	 * Test if the main plugin uses this protocol for the URLs of this platform, but not for URLs of a preset.
	 *
	 * @return void
	 */
	public function test_get_protocol_object_for_url(): void {
		// the protocols are only available for users with the capability for the platforms.
		$this->do_login();
		\ExternalFilesFromAwsS3\Platforms\S3Compatible::get_instance()->init();
		HetznerObjectStorage::get_instance()->init();
		$this->assertContains( 'ExternalFilesFromAwsS3\Platforms\S3Compatible\Protocol', Protocols::get_instance()->get_protocols() );

		// the URL with the fields of the generic storage.
		$fields   = $this->set_platform_fields( \ExternalFilesFromAwsS3\Platforms\S3Compatible::get_instance(), $this->field_values );
		$protocol = Protocols::get_instance()->get_protocol_object_for_url( 'https://s3.example.com/my-bucket/dir/example.jpg', $fields );
		$this->assertInstanceOf( \ExternalFilesFromAwsS3\Platforms\S3Compatible\Protocol::class, $protocol );
		$this->assertNotInstanceOf( \ExternalFilesFromAwsS3\Platforms\HetznerObjectStorage\Protocol::class, $protocol );
		\ExternalFilesFromAwsS3\Platforms\S3Compatible::get_instance()->set_fields( array() );

		// the URL with the fields of the preset.
		$fields   = $this->set_platform_fields(
			HetznerObjectStorage::get_instance(),
			array(
				'region'     => 'fsn1',
				'access_key' => 'key',
				'secret'     => 'secret',
				'bucket'     => 'my-bucket',
			)
		);
		$protocol = Protocols::get_instance()->get_protocol_object_for_url( 'https://my-bucket.fsn1.your-objectstorage.com/dir/example.jpg', $fields );
		$this->assertInstanceOf( \ExternalFilesFromAwsS3\Platforms\HetznerObjectStorage\Protocol::class, $protocol );
		HetznerObjectStorage::get_instance()->set_fields( array() );

		// other URLs are handled by the protocols of the main plugin.
		$this->assertNotInstanceOf( \ExternalFilesFromAwsS3\Protocol_Base::class, Protocols::get_instance()->get_protocol_object_for_url( 'https://example.com/example.jpg' ) );
	}
}
