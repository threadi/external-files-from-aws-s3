<?php
/**
 * Tests for class ExternalFilesFromAwsS3\Platform_Base.
 *
 * @package external-files-from-aws-s3
 */

namespace ExternalFilesFromAwsS3\Tests\Unit;

use Aws\S3\S3Client;
use ExternalFilesFromAwsS3\Platforms\BackblazeS3;
use ExternalFilesFromAwsS3\Platforms\CloudflareR2;
use ExternalFilesFromAwsS3\Platforms\DigitalOceanSpaces;
use ExternalFilesFromAwsS3\Tests\externalFilesFromAwsS3Tests;
use ExternalFilesInMediaLibrary\ExternalFiles\Protocols\Http;

/**
 * Object to test functions in class ExternalFilesFromAwsS3\Platform_Base.
 */
class Platform_Base extends externalFilesFromAwsS3Tests {
	/**
	 * The URL of the main directory for the tests.
	 *
	 * @var string
	 */
	private string $main = 'https://my-bucket.s3.us-west-004.backblazeb2.com/';

	/**
	 * Return the platform object for the tests with fields.
	 *
	 * @return BackblazeS3
	 */
	private function get_platform(): BackblazeS3 {
		$platform = BackblazeS3::get_instance();
		$this->set_platform_fields(
			$platform,
			array(
				'access_key' => 'key',
				'secret'     => 'secret',
				'bucket'     => 'my-bucket',
				'region'     => 'us-west-004',
			)
		);
		return $platform;
	}

	/**
	 * Return a node of the tree.
	 *
	 * @param string                           $title The title.
	 * @param array<string,array<string,mixed>> $dirs  The sub-directories.
	 *
	 * @return array<string,mixed>
	 */
	private function get_node( string $title, array $dirs = array() ): array {
		return array(
			'title' => $title,
			'files' => array( array( 'title' => $title . '.jpg' ) ),
			'dirs'  => $dirs,
		);
	}

	/**
	 * Return a tree of directories like the directory listing builds it.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function get_tree(): array {
		return array(
			$this->main => $this->get_node(
				$this->main,
				array(
					$this->main . 'a/' => $this->get_node(
						'a',
						array(
							$this->main . 'a/b/' => $this->get_node( 'b' ),
						)
					),
					$this->main . 'z/' => $this->get_node( 'z' ),
				)
			),
		);
	}

	/**
	 * Test if the protocol of each platform is added before the other protocols.
	 *
	 * @return void
	 */
	public function test_add_protocol(): void {
		$protocols = array( 'ExternalFilesInMediaLibrary\ExternalFiles\Protocols\Http' );
		foreach ( self::$platforms as $platform_class_name ) {
			$result = $platform_class_name::get_instance()->add_protocol( $protocols );
			$this->assertCount( 2, $result );
			$this->assertEquals( $platform_class_name . '\Protocol', $result[0] );
			$this->assertEquals( 'ExternalFilesInMediaLibrary\ExternalFiles\Protocols\Http', $result[1] );
			$this->assertTrue( class_exists( $result[0] ) );
		}
	}

	/**
	 * Test if each platform has a working configuration object.
	 *
	 * @return void
	 */
	public function test_get_configuration_object_name(): void {
		foreach ( self::$platforms as $platform_class_name ) {
			$name = $platform_class_name::get_instance()->get_configuration_object_name();
			$this->assertEquals( $platform_class_name . '\Configuration', $name );
			$this->assertInstanceOf( \ExternalFilesInMediaLibrary\Plugin\Configuration_Base::class, new $name() );
		}
	}

	/**
	 * Test if the S3 client is created with the given configuration.
	 *
	 * @return void
	 */
	public function test_get_the_client(): void {
		$client = $this->get_platform()->get_the_client(
			array(
				'version'     => 'latest',
				'region'      => 'eu-west-1',
				'credentials' => array(
					'key'    => 'key',
					'secret' => 'secret',
				),
			)
		);
		$this->assertInstanceOf( S3Client::class, $client );
		$this->assertEquals( 'eu-west-1', $client->getRegion() );
	}

	/**
	 * Test if the tree of other services is not changed.
	 *
	 * @return void
	 */
	public function test_prepare_tree_building_for_other_service(): void {
		$tree = array( 'example' => array( 'dirs' => array() ) );
		$this->assertEquals( $tree, $this->get_platform()->prepare_tree_building( $tree, 'https://example.com/', 'dropbox' ) );
	}

	/**
	 * Test the tree for the main directory.
	 *
	 * @return void
	 */
	public function test_prepare_tree_building_for_main_directory(): void {
		$platform = $this->get_platform();
		$platform->set_directory( $platform->get_directory() );

		// test 1: a complete tree is not changed.
		$this->assertEquals( $this->get_tree(), $platform->prepare_tree_building( $this->get_tree(), $this->main, 'backblaze-s3' ) );

		// test 2: a directory beside the main directory is moved into the main directory.
		$tree                     = $this->get_tree();
		$tree[ $this->main . 'c/' ] = $this->get_node( 'c' );
		$result                   = $platform->prepare_tree_building( $tree, $this->main, 'backblaze-s3' );
		$this->assertEquals( array( $this->main ), array_keys( $result ) );
		$this->assertArrayHasKey( $this->main . 'c/', $result[ $this->main ]['dirs'] );
		$this->assertEquals( 'c', $result[ $this->main ]['dirs'][ $this->main . 'c/' ]['title'] );
	}

	/**
	 * Test the tree for a sub-directory.
	 *
	 * @return void
	 */
	public function test_prepare_tree_building_for_sub_directory(): void {
		$platform = $this->get_platform();

		// test 1: the sub-directory is returned.
		$platform->set_directory( $this->main . 'z/' );
		$result = $platform->prepare_tree_building( $this->get_tree(), $this->main, 'backblaze-s3' );
		$this->assertEquals( array( $this->main . 'z/' ), array_keys( $result ) );
		$this->assertEquals( 'z', $result[ $this->main . 'z/' ]['title'] );

		// test 2: the complete tree is returned if the directory does not exist.
		$platform->set_directory( $this->main . 'not-existing/' );
		$this->assertEquals( $this->get_tree(), $platform->prepare_tree_building( $this->get_tree(), $this->main, 'backblaze-s3' ) );
	}

	/**
	 * Test the tree for a deeper sub-directory.
	 *
	 *
	 * @return void
	 */
	public function test_prepare_tree_building_for_deeper_sub_directory(): void {
		$platform = $this->get_platform();
		$platform->set_directory( $this->main . 'a/b/' );
		$result = $platform->prepare_tree_building( $this->get_tree(), $this->main, 'backblaze-s3' );
		$this->assertEquals( array( $this->main . 'a/b/' ), array_keys( $result ) );
	}

	/**
	 * Test if the query for files is only changed for a sub-directory.
	 *
	 * @return void
	 */
	public function test_change_file_query(): void {
		$platform = $this->get_platform();
		$query    = array( 'Bucket' => 'my-bucket' );

		// test 1: without directory.
		$platform->set_directory( '' );
		$this->assertEquals( $query, $platform->change_file_query( $query ) );

		// test 2: the main directory.
		$platform->set_directory( $this->main );
		$this->assertEquals( $query, $platform->change_file_query( $query ) );

		// test 3: a sub-directory.
		$platform->set_directory( $this->main . 'documents/2024/' );
		$result = $platform->change_file_query( $query );
		$this->assertEquals( 'my-bucket', $result['Bucket'] );
		$this->assertEquals( 'documents/2024/', $result['Prefix'] );
		$this->assertEquals( '/', $result['Delimiter'] );
	}

	/**
	 * Test the query for a sub-directory on each platform.
	 *
	 * @return void
	 */
	public function test_change_file_query_for_each_platform(): void {
		$platforms = array(
			array( CloudflareR2::class, array( 'account_id' => 'account123', 'bucket' => 'my-bucket' ), 'https://dash.cloudflare.com/account123/documents/' ),
			array( DigitalOceanSpaces::class, array( 'bucket' => 'my-bucket', 'region' => 'fra1' ), 'https://my-bucket.fra1.digitaloceanspaces.com/documents/' ),
			array( BackblazeS3::class, array( 'bucket' => 'my-bucket', 'region' => 'us-west-004' ), 'https://my-bucket.s3.us-west-004.backblazeb2.com/documents/' ),
		);
		foreach ( $platforms as $platform_data ) {
			$platform = $platform_data[0]::get_instance();
			$this->set_platform_fields( $platform, $platform_data[1] );
			$platform->set_directory( $platform_data[2] );
			$this->assertEquals( 'documents/', $platform->change_file_query( array() )['Prefix'], 'Wrong prefix for ' . $platform_data[0] );
		}
	}

	/**
	 * Test the removal of the authorization header in the base object.
	 *
	 * @return void
	 */
	public function test_remove_authorization_header(): void {
		$args   = array(
			'headers' => array(
				'Authorization' => 'Basic abc',
				'Accept'        => '*/*',
			),
		);
		$result = $this->get_platform()->remove_authorization_header( $args, new Http( $this->main . 'example.jpg' ) );
		$this->assertArrayNotHasKey( 'Authorization', $result['headers'] );
		$this->assertEquals( '*/*', $result['headers']['Accept'] );
	}

	/**
	 * Test if the service name of a file is only changed for an import with this service.
	 *
	 * @return void
	 */
	public function test_change_service_name(): void {
		$attachment_id = self::factory()->attachment->create( array( 'post_mime_type' => 'image/jpeg' ) );
		$file          = \ExternalFilesInMediaLibrary\ExternalFiles\Files::get_instance()->get_file( $attachment_id );
		$file->set_service_name( 'dropbox' );

		// without a service in the request nothing is changed.
		$this->get_platform()->change_service_name( $file );
		$this->assertEquals( 'dropbox', $file->get_service_name() );
	}

	/**
	 * Test if the forward URL is the URL of the directory listing.
	 *
	 * @return void
	 */
	public function test_get_forward_url(): void {
		$url = $this->get_platform()->get_forward_url();
		$this->assertIsString( $url );
		$this->assertStringContainsString( 'wp-admin', $url );
	}

	/**
	 * Test if the authorization header is never removed by the base object, as it does not have own URLs.
	 *
	 * @return void
	 */
	public function test_remove_authorization_header_in_base_object(): void {
		$platform = $this->getMockBuilder( \ExternalFilesFromAwsS3\Platform_Base::class )->disableOriginalConstructor()->onlyMethods( array() )->getMock();
		$args     = array( 'headers' => array( 'Authorization' => 'Basic abc' ) );
		$this->assertFalse( $platform->is_platform_url( 'https://example.com/example.jpg' ) );
		$this->assertEquals( $args, $platform->remove_authorization_header( $args, new Http( 'https://example.com/example.jpg' ) ) );
	}

	/**
	 * Test the authorization header for HTTP requests of the main plugin, if all platforms are active.
	 *
	 * The hook is used for each HTTP request, so no platform may remove the authorization for other URLs.
	 *
	 * @return void
	 */
	public function test_authorization_header_with_all_platforms(): void {
		$this->do_login();
		foreach ( self::$platforms as $platform_class_name ) {
			$platform_class_name::get_instance()->init();
		}

		$args = array( 'headers' => array( 'Authorization' => 'Basic abc' ) );
		$http = new Http( 'https://example.com/protected/example.jpg' );
		$this->assertArrayHasKey( 'Authorization', apply_filters( 'efml_http_header_args', $args, $http )['headers'] );
	}
}
