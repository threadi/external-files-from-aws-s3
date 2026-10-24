<?php
/**
 * Tests for class ExternalFilesFromAwsS3\Platforms\CloudflareR2\Protocol.
 *
 * @package external-files-from-aws-s3
 */

namespace ExternalFilesFromAwsS3\Tests\Unit\Platforms\CloudflareR2;

use ExternalFilesFromAwsS3\Tests\externalFilesFromAwsS3Tests;
use ExternalFilesInMediaLibrary\ExternalFiles\Protocols;

/**
 * Object to test functions in class ExternalFilesFromAwsS3\Platforms\CloudflareR2\Protocol.
 */
class Protocol extends externalFilesFromAwsS3Tests {
	/**
	 * The values for the fields we use in the tests.
	 *
	 * @var array<string,mixed>
	 */
	private array $field_values = array(
		'account_id'    => 'account123',
		'eu'            => 0,
		'access_key'    => 'key',
		'secret'        => 'secret',
		'bucket'        => 'my-bucket',
		'public_domain' => '',
	);

	/**
	 * Return the protocol object for the given URL.
	 *
	 * @param string              $url    The URL.
	 * @param array<string,mixed> $values Additional values for the fields.
	 *
	 * @return \ExternalFilesFromAwsS3\Platforms\CloudflareR2\Protocol
	 */
	private function get_protocol( string $url, array $values = array() ): \ExternalFilesFromAwsS3\Platforms\CloudflareR2\Protocol {
		$fields   = $this->set_platform_fields( \ExternalFilesFromAwsS3\Platforms\CloudflareR2::get_instance(), array_merge( $this->field_values, $values ) );
		$protocol = new \ExternalFilesFromAwsS3\Platforms\CloudflareR2\Protocol( $url );
		$protocol->set_fields( $fields );
		return $protocol;
	}

	/**
	 * Test the name and the title of the protocol.
	 *
	 * @return void
	 */
	public function test_get_name_and_title(): void {
		$protocol = $this->get_protocol( 'https://dash.cloudflare.com/account123/r2/buckets/my-bucket/objects/dir/example.jpg' );
		$this->assertEquals( 'cloudflare-r2', $protocol->get_name() );
		$this->assertEquals( 'Cloudflare R2', $protocol->get_title() );
		$this->assertEquals( \ExternalFilesFromAwsS3\Platforms\CloudflareR2::get_instance()->get_name(), $protocol->get_name() );
	}

	/**
	 * Test the general settings of this protocol.
	 *
	 * @return void
	 */
	public function test_settings(): void {
		$protocol = $this->get_protocol( 'https://dash.cloudflare.com/account123/r2/buckets/my-bucket/objects/dir/example.jpg' );
		$this->assertTrue( $protocol->is_available() );
		$this->assertFalse( $protocol->can_check_availability() );
		$this->assertFalse( $protocol->is_url_reachable() );
		$this->assertTrue( $protocol->check_url( 'https://dash.cloudflare.com/account123/r2/buckets/my-bucket/objects/dir/example.jpg' ) );
		$this->assertFalse( $protocol->check_url( '' ) );
	}

	/**
	 * Test which URLs are compatible with this protocol.
	 *
	 * @return void
	 */
	public function test_is_url_compatible(): void {
		$this->assertTrue( $this->get_protocol( 'https://dash.cloudflare.com/account123/r2/buckets/my-bucket/objects/dir/example.jpg' )->is_url_compatible() );
		$this->assertTrue( $this->get_protocol( 'https://dash.cloudflare.com/account123/' )->is_url_compatible() );
		$this->assertFalse( $this->get_protocol( 'https://example.com/example.jpg' )->is_url_compatible() );
		$this->assertFalse( $this->get_protocol( 'ftp://example.com/example.jpg' )->is_url_compatible() );
	}

	/**
	 * Test if the key of a file is extracted from its URL.
	 *
	 * @return void
	 */
	public function test_get_key_of_file(): void {
		$this->assertEquals( 'dir/example.jpg', $this->call_method( $this->get_protocol( 'https://dash.cloudflare.com/account123/r2/buckets/my-bucket/objects/dir/example.jpg' ), 'get_key_of_file', array( 'https://dash.cloudflare.com/account123/r2/buckets/my-bucket/objects/dir/example.jpg' ) ) );
		$this->assertEquals( 'https://example.com/dir/example.jpg', $this->call_method( $this->get_protocol( 'https://example.com/dir/example.jpg' ), 'get_key_of_file', array( 'https://example.com/dir/example.jpg' ) ) );
	}

	/**
	 * Test if the main plugin uses this protocol for the URLs of this platform.
	 *
	 * @return void
	 */
	public function test_get_protocol_object_for_url(): void {
		// the protocol is only available for users with the capability for this platform.
		$this->do_login();
		\ExternalFilesFromAwsS3\Platforms\CloudflareR2::get_instance()->init();
		$this->assertContains( 'ExternalFilesFromAwsS3\Platforms\CloudflareR2\Protocol', Protocols::get_instance()->get_protocols() );

		$fields   = $this->set_platform_fields( \ExternalFilesFromAwsS3\Platforms\CloudflareR2::get_instance(), $this->field_values );
		$protocol = Protocols::get_instance()->get_protocol_object_for_url( 'https://dash.cloudflare.com/account123/r2/buckets/my-bucket/objects/dir/example.jpg', $fields );
		$this->assertInstanceOf( \ExternalFilesFromAwsS3\Platforms\CloudflareR2\Protocol::class, $protocol );
		$this->assertEquals( $fields, $protocol->get_fields() );

		// other URLs are handled by the protocols of the main plugin.
		$this->assertNotInstanceOf( \ExternalFilesFromAwsS3\Protocol_Base::class, Protocols::get_instance()->get_protocol_object_for_url( 'https://example.com/example.jpg' ) );
	}

	/**
	 * Test the URLs with the EU storage.
	 *
	 * @return void
	 */
	public function test_get_key_of_file_with_eu_storage(): void {
		$protocol = $this->get_protocol( 'https://dash.cloudflare.com/account123/r2/eu/buckets/my-bucket/objects/dir/example.jpg', array( 'eu' => 1 ) );
		$this->assertEquals( 'dir/example.jpg', $this->call_method( $protocol, 'get_key_of_file', array( 'https://dash.cloudflare.com/account123/r2/eu/buckets/my-bucket/objects/dir/example.jpg' ) ) );
	}

	/**
	 * Test the URLs of a custom domain.
	 *
	 * @return void
	 */
	public function test_custom_domain(): void {
		foreach ( array( 'cdn.example.com', 'https://cdn.example.com/' ) as $domain ) {
			foreach ( array( 'https://cdn.example.com/dir/example.jpg', 'http://cdn.example.com/dir/example.jpg' ) as $url ) {
				$protocol = $this->get_protocol( $url, array( 'public_domain' => $domain ) );

				// the URL is compatible.
				$this->assertTrue( $protocol->is_url_compatible(), 'URL ' . $url . ' is not compatible with domain ' . $domain );

				// files of a custom domain are external hosted and could change their hosting.
				$this->assertFalse( $protocol->should_be_saved_local() );
				$this->assertTrue( $protocol->can_change_hosting() );

				// the key is extracted from the URL.
				$this->assertEquals( 'dir/example.jpg', $this->call_method( $protocol, 'get_key_of_file', array( $url ) ) );
			}
		}

		// URLs of other domains are not compatible.
		$protocol = $this->get_protocol( 'https://other.example.com/dir/example.jpg', array( 'public_domain' => 'cdn.example.com' ) );
		$this->assertFalse( $protocol->is_url_compatible() );
	}

	/**
	 * Test if files without custom domain are saved local and could not change their hosting.
	 *
	 * @return void
	 */
	public function test_hosting(): void {
		$protocol = $this->get_protocol( 'https://dash.cloudflare.com/account123/r2/buckets/my-bucket/objects/dir/example.jpg' );
		$this->assertTrue( $protocol->should_be_saved_local() );
		$this->assertFalse( $protocol->can_change_hosting() );
	}
}
