<?php
/**
 * Tests for the connection test of the platforms via the connection test of External Files in Media Library.
 *
 * @package external-files-from-aws-s3
 */

namespace ExternalFilesFromAwsS3\Tests\Unit\Plugin;

use ExternalFilesFromAwsS3\Platforms\AwsS3;
use ExternalFilesFromAwsS3\Platforms\HetznerObjectStorage;
use ExternalFilesFromAwsS3\Platforms\S3Compatible;
use ExternalFilesFromAwsS3\Tests\externalFilesFromAwsS3Tests;
use WPAjaxDieContinueException;

/**
 * Object to test the connection test of the platforms via AJAX.
 */
class Connection_Test extends externalFilesFromAwsS3Tests {
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
	);

	/**
	 * Cleanup after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		$_POST    = array();
		$_REQUEST = array();
		remove_all_filters( 'wp_die_ajax_handler' );
		remove_filter( 'wp_doing_ajax', '__return_true' );
		parent::tear_down();
	}

	/**
	 * Run the AJAX request for the connection test and return the resulting dialog.
	 *
	 * @param array<string,mixed> $request The request data.
	 *
	 * @return array<string,mixed>
	 */
	private function run_ajax( array $request ): array {
		// prepare the request.
		$request  = array_merge( array( 'nonce' => wp_create_nonce( 'efml_connection_test' ) ), $request );
		$_POST    = $request;
		$_REQUEST = $request;

		// stop at the end of the request instead of exit.
		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter(
			'wp_die_ajax_handler',
			static function () {
				return static function ( $message ) {
					throw new WPAjaxDieContinueException( is_scalar( $message ) ? (string) $message : '' );
				};
			}
		);

		// run the request.
		ob_start();
		try {
			\ExternalFilesInMediaLibrary\Plugin\Connection_Test::get_instance()->run_via_ajax();
		} catch ( WPAjaxDieContinueException $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// the request has been finished.
		}
		$output = (string) ob_get_clean();

		// return the dialog.
		$result = json_decode( $output, true );
		$this->assertIsArray( $result, 'No JSON returned: ' . $output );
		return $result;
	}

	/**
	 * Test that all platforms are found by the connection test of the main plugin.
	 *
	 * @return void
	 */
	public function test_get_service_by_name(): void {
		$instance = \ExternalFilesInMediaLibrary\Plugin\Connection_Test::get_instance();
		$this->assertSame( S3Compatible::get_instance(), $instance->get_service_by_name( 's3-compatible' ) );
		$this->assertSame( HetznerObjectStorage::get_instance(), $instance->get_service_by_name( 'hetzner-object-storage' ) );
		$this->assertSame( AwsS3::get_instance(), $instance->get_service_by_name( AwsS3::get_instance()->get_name() ) );
	}

	/**
	 * Test that the platform has no own connection test anymore, but uses the one of the main plugin.
	 *
	 * @return void
	 */
	public function test_no_own_connection_test(): void {
		$this->assertFalse( has_action( 'wp_ajax_efmlawss3_connection_test' ) );
		$this->assertFalse( class_exists( '\ExternalFilesFromAwsS3\Plugin\Connection_Test' ) );
	}

	/**
	 * Test the connection test via AJAX with the fields from the request.
	 *
	 * @return void
	 */
	public function test_run_via_ajax(): void {
		$this->do_login();
		$s3 = $this->use_s3_mock(
			S3Compatible::class,
			array(
				'ListObjectsV2' => array( 'Contents' => array() ),
				'PutObject'     => array( 'ObjectURL' => 'https://s3.example.com/my-bucket/test.txt' ),
				'DeleteObject'  => array(),
			)
		);
		$fields   = $this->set_platform_fields( $s3, $this->field_values );
		$callback = $this->add_http_response_for_prefix( 'https://s3.example.com/my-bucket/efml-connection-test-', 200 );

		$dialog = $this->run_ajax(
			array(
				'type'   => 's3-compatible',
				'fields' => $fields,
			)
		);
		remove_filter( 'pre_http_request', $callback, 5 );

		$this->assertEquals( 'Connection test successful', $dialog['detail']['title'] );
		$this->assertStringContainsString( 'Upload a test file', $dialog['detail']['texts'][0] );
		$this->assertStringNotContainsString( 'Failed', $dialog['detail']['texts'][0] );
		$this->assertEquals( array( 'ListObjectsV2', 'PutObject', 'DeleteObject' ), $this->get_s3_command_names() );
	}

	/**
	 * Test the connection test via AJAX with a failure.
	 *
	 * @return void
	 */
	public function test_run_via_ajax_with_failure(): void {
		$this->do_login();
		$s3     = $this->use_s3_mock( S3Compatible::class, array( 'ListObjectsV2' => $this->get_s3_exception( 'ListObjectsV2', 403, 'InvalidAccessKeyId' ) ) );
		$fields = $this->set_platform_fields( $s3, $this->field_values );

		$dialog = $this->run_ajax(
			array(
				'type'   => 's3-compatible',
				'fields' => $fields,
			)
		);

		$this->assertEquals( 'Connection test failed', $dialog['detail']['title'] );
		$this->assertStringContainsString( 'Check the access key.', $dialog['detail']['texts'][0] );
	}

	/**
	 * Test the connection test via AJAX without the capability for the platform.
	 *
	 * @return void
	 */
	public function test_run_via_ajax_not_allowed(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$dialog = $this->run_ajax( array( 'type' => 's3-compatible' ) );
		$this->assertStringContainsString( 'could not be run', $dialog['detail']['texts'][0] );
		$this->assertEmpty( $this->get_s3_commands() );
	}
}
