<?php
/**
 * Tests for class ExternalFilesFromAwsS3\Plugin\Updates.
 *
 * @package external-files-from-aws-s3
 */

namespace ExternalFilesFromAwsS3\Tests\Unit\Plugin;

use ExternalFilesFromAwsS3\Tests\externalFilesFromAwsS3Tests;
use stdClass;

/**
 * Object to test functions in class ExternalFilesFromAwsS3\Plugin\Updates.
 */
class Updates extends externalFilesFromAwsS3Tests {
	/**
	 * The URL of the GitHub API for the latest release.
	 *
	 * @var string
	 */
	private string $api_url = 'https://api.github.com/repos/threadi/external-files-from-aws-s3/releases/latest';

	/**
	 * Return the release data like the GitHub API returns them.
	 *
	 * @param string                         $tag_name The tag name.
	 * @param array<int,array<string,string>> $assets  The assets of the release.
	 *
	 * @return string
	 */
	private function get_release_json( string $tag_name, array $assets ): string {
		return (string) wp_json_encode(
			array(
				'tag_name' => $tag_name,
				'assets'   => $assets,
			)
		);
	}

	/**
	 * Return a ZIP asset like the GitHub API returns it.
	 *
	 * @param string $version The version.
	 *
	 * @return array<string,string>
	 */
	private function get_zip_asset( string $version ): array {
		return array(
			'content_type'         => 'application/zip',
			'browser_download_url' => 'https://github.com/threadi/external-files-from-aws-s3/releases/download/' . $version . '/external-files-from-aws-s3_' . $version . '.zip',
		);
	}

	/**
	 * Test if the returned object is always the same.
	 *
	 * @return void
	 */
	public function test_get_instance(): void {
		$instance = \ExternalFilesFromAwsS3\Plugin\Updates::get_instance();
		$this->assertInstanceOf( \ExternalFilesFromAwsS3\Plugin\Updates::class, $instance );
		$this->assertSame( $instance, \ExternalFilesFromAwsS3\Plugin\Updates::get_instance() );
	}

	/**
	 * Test if a new release on GitHub is added as update.
	 *
	 * @return void
	 */
	public function test_check_with_new_release(): void {
		$this->add_http_response( $this->api_url, 200, array( 'Content-Type' => 'application/json' ), $this->get_release_json( 'v99.0.0', array( array( 'content_type' => 'text/plain', 'browser_download_url' => 'https://example.com/readme.txt' ), $this->get_zip_asset( '99.0.0' ) ) ) );

		$data = \ExternalFilesFromAwsS3\Plugin\Updates::get_instance()->check( new stdClass() );
		$slug = plugin_basename( EFMLAWSS3_PLUGIN );

		$this->assertIsObject( $data );
		$this->assertArrayHasKey( $slug, $data->response );
		$this->assertEquals( '99.0.0', $data->response[ $slug ]->new_version );
		$this->assertEquals( 'external-files-from-aws-s3', $data->response[ $slug ]->slug );
		$this->assertEquals( $slug, $data->response[ $slug ]->plugin );
		$this->assertStringEndsWith( 'external-files-from-aws-s3_99.0.0.zip', $data->response[ $slug ]->package );
		$this->assertCount( 1, $this->get_http_requests() );
	}

	/**
	 * Test if an existing update information is not changed and GitHub is not requested.
	 *
	 * @return void
	 */
	public function test_check_with_existing_data(): void {
		$slug                   = plugin_basename( EFMLAWSS3_PLUGIN );
		$existing               = new stdClass();
		$existing->new_version  = '1.0.0';
		$input                  = new stdClass();
		$input->response        = array( $slug => $existing );

		$data = \ExternalFilesFromAwsS3\Plugin\Updates::get_instance()->check( $input );

		$this->assertSame( $existing, $data->response[ $slug ] );
		$this->assertEmpty( $this->get_http_requests() );
	}

	/**
	 * Test if nothing is changed if GitHub does not answer with HTTP status 200.
	 *
	 * @return void
	 */
	public function test_check_with_error_from_github(): void {
		$this->add_http_response( $this->api_url, 500 );

		$data = \ExternalFilesFromAwsS3\Plugin\Updates::get_instance()->check( new stdClass() );

		$this->assertObjectNotHasProperty( 'response', $data );
		$this->assertObjectNotHasProperty( 'no_update', $data );
	}

	/**
	 * Test if nothing is changed if the release does not have any assets.
	 *
	 * @return void
	 */
	public function test_check_without_assets(): void {
		$this->add_http_response( $this->api_url, 200, array( 'Content-Type' => 'application/json' ), $this->get_release_json( 'v99.0.0', array() ) );

		$data = \ExternalFilesFromAwsS3\Plugin\Updates::get_instance()->check( new stdClass() );

		$this->assertObjectNotHasProperty( 'response', $data );
		$this->assertObjectNotHasProperty( 'no_update', $data );
	}

	/**
	 * Test if no update is set if the release does not contain a ZIP file.
	 *
	 * @return void
	 */
	public function test_check_without_zip_asset(): void {
		$this->add_http_response( $this->api_url, 200, array( 'Content-Type' => 'application/json' ), $this->get_release_json( 'v99.0.0', array( array( 'content_type' => 'text/plain', 'browser_download_url' => 'https://example.com/readme.txt' ) ) ) );

		$data = \ExternalFilesFromAwsS3\Plugin\Updates::get_instance()->check( new stdClass() );

		$this->assertObjectNotHasProperty( 'response', $data );
	}

	/**
	 * Test if nothing is changed if GitHub answers with invalid JSON.
	 *
	 * @return void
	 */
	public function test_check_with_invalid_json(): void {
		$this->add_http_response( $this->api_url, 200, array(), 'no json' );

		$data = \ExternalFilesFromAwsS3\Plugin\Updates::get_instance()->check( new stdClass() );

		$this->assertObjectNotHasProperty( 'response', $data );
		$this->assertObjectNotHasProperty( 'no_update', $data );
	}
}
