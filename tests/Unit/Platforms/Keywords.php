<?php
/**
 * Tests for the search terms of the platforms.
 *
 * @package external-files-from-aws-s3
 */

namespace ExternalFilesFromAwsS3\Tests\Unit\Platforms;

use ExternalFilesFromAwsS3\Platforms\AwsS3;
use ExternalFilesFromAwsS3\Platforms\HetznerObjectStorage;
use ExternalFilesFromAwsS3\Platforms\S3Compatible;
use ExternalFilesFromAwsS3\Tests\externalFilesFromAwsS3Tests;

/**
 * Object to test the search terms of the platforms, e.g. for the command palette.
 */
class Keywords extends externalFilesFromAwsS3Tests {
	/**
	 * Test the search terms.
	 *
	 * @return void
	 */
	public function test_keywords(): void {
		// bail if the main plugin does not support search terms.
		if ( ! method_exists( AwsS3::get_instance(), 'get_search_keywords' ) ) {
			$this->markTestSkipped( 'Requires "External Files in Media Library" 6.0.0 or newer.' );
		}

		$this->assertContains( 'CloudFront', AwsS3::get_instance()->get_search_keywords() );
		$this->assertContains( 'MinIO', S3Compatible::get_instance()->get_search_keywords() );

		// the presets use the terms of all platforms and their label.
		$keywords = HetznerObjectStorage::get_instance()->get_search_keywords();
		$this->assertContains( 'S3', $keywords );
		$this->assertTrue( \ExternalFilesInMediaLibrary\Plugin\Helper::is_search_matching( 'hetzner', $keywords ) );
	}
}
