<?php
/**
 * Tests for class ExternalFilesFromAwsS3\Plugin\Uninstall.
 *
 * @package external-files-from-aws-s3
 */

namespace ExternalFilesFromAwsS3\Tests\Unit\Plugin;

use ExternalFilesFromAwsS3\Tests\externalFilesFromAwsS3Tests;

/**
 * Object to test functions in class ExternalFilesFromAwsS3\Plugin\Uninstall.
 */
class Uninstall extends externalFilesFromAwsS3Tests {
	/**
	 * Test if the returned object is always the same.
	 *
	 * @return void
	 */
	public function test_get_instance(): void {
		$instance = \ExternalFilesFromAwsS3\Plugin\Uninstall::get_instance();
		$this->assertInstanceOf( \ExternalFilesFromAwsS3\Plugin\Uninstall::class, $instance );
		$this->assertSame( $instance, \ExternalFilesFromAwsS3\Plugin\Uninstall::get_instance() );
	}

	/**
	 * Test if the uninstallation removes the cached list of AWS S3 regions.
	 *
	 * @return void
	 */
	public function test_run(): void {
		// create the cached list of regions.
		set_transient( 'efml_aws_s3_regions', array( 'eu-central-1' => 'Europe (Frankfurt)' ), WEEK_IN_SECONDS );
		$this->assertIsArray( get_transient( 'efml_aws_s3_regions' ) );

		// run the uninstallation.
		\ExternalFilesFromAwsS3\Plugin\Uninstall::get_instance()->run();

		// check the result.
		$this->assertFalse( get_transient( 'efml_aws_s3_regions' ) );
	}
}
