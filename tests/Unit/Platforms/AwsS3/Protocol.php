<?php
/**
 * Tests for class ExternalFilesFromAwsS3\Platforms\AwsS3\Protocol.
 *
 * @package external-files-from-aws-s3
 */

namespace ExternalFilesFromAwsS3\Tests\Unit\Platforms\AwsS3;

use ExternalFilesFromAwsS3\Tests\externalFilesFromAwsS3Tests;
use ExternalFilesInMediaLibrary\ExternalFiles\Protocols;

/**
 * Object to test functions in class ExternalFilesFromAwsS3\Platforms\AwsS3\Protocol.
 */
class Protocol extends externalFilesFromAwsS3Tests {
	/**
	 * The values for the fields we use in the tests.
	 *
	 * @var array<string,mixed>
	 */
	private array $field_values = array(
		'access_key' => 'key',
		'secret'     => 'secret',
		'bucket'     => 'my-bucket',
		'region'     => 'eu-central-1',
	);

	/**
	 * Return the protocol object for the given URL.
	 *
	 * @param string              $url    The URL.
	 * @param array<string,mixed> $values Additional values for the fields.
	 *
	 * @return \ExternalFilesFromAwsS3\Platforms\AwsS3\Protocol
	 */
	private function get_protocol( string $url, array $values = array() ): \ExternalFilesFromAwsS3\Platforms\AwsS3\Protocol {
		$fields   = $this->set_platform_fields( \ExternalFilesFromAwsS3\Platforms\AwsS3::get_instance(), array_merge( $this->field_values, $values ) );
		$protocol = new \ExternalFilesFromAwsS3\Platforms\AwsS3\Protocol( $url );
		$protocol->set_fields( $fields );
		return $protocol;
	}

	/**
	 * Test the name and the title of the protocol.
	 *
	 * @return void
	 */
	public function test_get_name_and_title(): void {
		$protocol = $this->get_protocol( 'https://my-bucket.s3.eu-central-1.amazonaws.com/dir/example.jpg' );
		$this->assertEquals( 'aws-s3', $protocol->get_name() );
		$this->assertEquals( 'AWS S3', $protocol->get_title() );
		$this->assertEquals( \ExternalFilesFromAwsS3\Platforms\AwsS3::get_instance()->get_name(), $protocol->get_name() );
	}

	/**
	 * Test the general settings of this protocol.
	 *
	 * @return void
	 */
	public function test_settings(): void {
		$protocol = $this->get_protocol( 'https://my-bucket.s3.eu-central-1.amazonaws.com/dir/example.jpg' );
		$this->assertTrue( $protocol->is_available() );
		$this->assertFalse( $protocol->can_check_availability() );
		$this->assertFalse( $protocol->is_url_reachable() );
		$this->assertTrue( $protocol->check_url( 'https://my-bucket.s3.eu-central-1.amazonaws.com/dir/example.jpg' ) );
		$this->assertFalse( $protocol->check_url( '' ) );
	}

	/**
	 * Test which URLs are compatible with this protocol.
	 *
	 * @return void
	 */
	public function test_is_url_compatible(): void {
		$this->assertTrue( $this->get_protocol( 'https://my-bucket.s3.eu-central-1.amazonaws.com/dir/example.jpg' )->is_url_compatible() );
		$this->assertTrue( $this->get_protocol( 'https://console.aws.amazon.com/s3/buckets/my-bucket/dir/' )->is_url_compatible() );
		$this->assertFalse( $this->get_protocol( 'https://example.com/example.jpg' )->is_url_compatible() );
		$this->assertFalse( $this->get_protocol( 'ftp://example.com/example.jpg' )->is_url_compatible() );
	}

	/**
	 * Test if the key of a file is extracted from its URL.
	 *
	 * @return void
	 */
	public function test_get_key_of_file(): void {
		$this->assertEquals( 'dir/example.jpg', $this->call_method( $this->get_protocol( 'https://my-bucket.s3.eu-central-1.amazonaws.com/dir/example.jpg' ), 'get_key_of_file', array( 'https://my-bucket.s3.eu-central-1.amazonaws.com/dir/example.jpg' ) ) );
		$this->assertEquals( 'https://other-bucket.s3.eu-central-1.amazonaws.com/example.jpg', $this->call_method( $this->get_protocol( 'https://other-bucket.s3.eu-central-1.amazonaws.com/example.jpg' ), 'get_key_of_file', array( 'https://other-bucket.s3.eu-central-1.amazonaws.com/example.jpg' ) ) );
	}

	/**
	 * Test if the main plugin uses this protocol for the URLs of this platform.
	 *
	 * @return void
	 */
	public function test_get_protocol_object_for_url(): void {
		// the protocol is only available for users with the capability for this platform.
		$this->do_login();
		\ExternalFilesFromAwsS3\Platforms\AwsS3::get_instance()->init();
		$this->assertContains( 'ExternalFilesFromAwsS3\Platforms\AwsS3\Protocol', Protocols::get_instance()->get_protocols() );

		$fields   = $this->set_platform_fields( \ExternalFilesFromAwsS3\Platforms\AwsS3::get_instance(), $this->field_values );
		$protocol = Protocols::get_instance()->get_protocol_object_for_url( 'https://my-bucket.s3.eu-central-1.amazonaws.com/dir/example.jpg', $fields );
		$this->assertInstanceOf( \ExternalFilesFromAwsS3\Platforms\AwsS3\Protocol::class, $protocol );
		$this->assertEquals( $fields, $protocol->get_fields() );

		// other URLs are handled by the protocols of the main plugin.
		$this->assertNotInstanceOf( \ExternalFilesFromAwsS3\Protocol_Base::class, Protocols::get_instance()->get_protocol_object_for_url( 'https://example.com/example.jpg' ) );
	}

	/**
	 * Test if files of AWS S3 are always saved local and could not change their hosting.
	 *
	 * @return void
	 */
	public function test_hosting(): void {
		$protocol = $this->get_protocol( 'https://my-bucket.s3.eu-central-1.amazonaws.com/dir/example.jpg' );
		$this->assertTrue( $protocol->should_be_saved_local() );
		$this->assertFalse( $protocol->can_change_hosting() );
	}
}
