<?php
/**
 * Tests for class ExternalFilesFromAwsS3\Plugin\Helper.
 *
 * @package external-files-from-aws-s3
 */

namespace ExternalFilesFromAwsS3\Tests\Unit\Plugin;

use ExternalFilesFromAwsS3\Tests\externalFilesFromAwsS3Tests;

/**
 * Object to test functions in class ExternalFilesFromAwsS3\Plugin\Helper.
 */
class Helper extends externalFilesFromAwsS3Tests {
	/**
	 * Test if the returning variable is the plugin directory with trailing slash.
	 *
	 * @return void
	 */
	public function test_get_plugin_dir(): void {
		$plugin_dir = \ExternalFilesFromAwsS3\Plugin\Helper::get_plugin_dir();
		$this->assertIsString( $plugin_dir );
		$this->assertStringEndsWith( '/', $plugin_dir );
		$this->assertFileExists( $plugin_dir . 'external-files-from-aws-s3.php' );
		$this->assertEquals( trailingslashit( TESTS_PLUGIN_DIR ), $plugin_dir );
	}

	/**
	 * Test if the returning variable is a URL with trailing slash.
	 *
	 * @return void
	 */
	public function test_get_plugin_url(): void {
		$plugin_url = \ExternalFilesFromAwsS3\Plugin\Helper::get_plugin_url();
		$this->assertIsString( $plugin_url );
		$this->assertStringStartsWith( 'http', $plugin_url );
		$this->assertStringEndsWith( '/', $plugin_url );
	}

	/**
	 * Test if the returning variable is the modification time of the file, as WP_DEBUG is enabled during tests.
	 *
	 * @return void
	 */
	public function test_get_file_version(): void {
		$file    = \ExternalFilesFromAwsS3\Plugin\Helper::get_plugin_dir() . 'admin/style.css';
		$version = \ExternalFilesFromAwsS3\Plugin\Helper::get_file_version( $file );
		$this->assertIsString( $version );
		$this->assertTrue( WP_DEBUG );
		$this->assertEquals( (string) filemtime( $file ), $version );
	}
}
