<?php
/**
 * Tests for the help for the setup of the platforms.
 *
 * @package external-files-from-aws-s3
 */

namespace ExternalFilesFromAwsS3\Tests\Unit;

use Aws\Command;
use Aws\Exception\AwsException;
use ExternalFilesFromAwsS3\Platforms\AwsS3;
use ExternalFilesFromAwsS3\Tests\externalFilesFromAwsS3Tests;

/**
 * Object to test the help for the setup of the platforms.
 */
class SetupGuide extends externalFilesFromAwsS3Tests {
	/**
	 * Cleanup after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_all_filters( 'efmlawss3_iam_policy' );
		parent::tear_down();
	}

	/**
	 * Return the names of all platforms.
	 *
	 * @return array<int,array<int,string>>
	 */
	public function platforms_provider(): array {
		$list = array();
		foreach ( array( 'AwsS3', 'BackblazeS3', 'CloudflareR2', 'DigitalOceanSpaces', 'HetznerObjectStorage', 'IonosObjectStorage', 'OvhcloudObjectStorage', 'S3Compatible', 'ScalewayObjectStorage', 'Wasabi' ) as $name ) {
			$list[] = array( '\\ExternalFilesFromAwsS3\\Platforms\\' . $name );
		}
		return $list;
	}

	/**
	 * Test that each platform has a help with steps and a link to the instructions of its provider.
	 *
	 * @dataProvider platforms_provider
	 *
	 * @param string $class_name The class name of the platform.
	 *
	 * @return void
	 */
	public function test_guide_of_platform( string $class_name ): void {
		$guide = $class_name::get_instance()->get_setup_guide();
		$this->assertNotEmpty( $guide['intro'] );
		$this->assertNotEmpty( $guide['steps'] );
		$this->assertNotEmpty( $guide['links'] );
		foreach ( $guide['links'] as $link ) {
			$this->assertStringStartsWith( 'https://', $link['url'] );
		}
		$this->assertStringContainsString( 'efml-setup-guide', $class_name::get_instance()->get_setup_guide_html() );
	}

	/**
	 * Test the IAM policy for AWS S3.
	 *
	 * @return void
	 */
	public function test_iam_policy(): void {
		$platform = AwsS3::get_instance();

		// without bucket: with placeholder and without CloudFront.
		$policy = json_decode( $platform->get_iam_policy( '' ), true );
		$this->assertSame( 'arn:aws:s3:::YOUR-BUCKET', $policy['Statement'][0]['Resource'] );
		$this->assertSame( 'arn:aws:s3:::YOUR-BUCKET/*', $policy['Statement'][1]['Resource'] );
		$this->assertContains( 's3:PutObjectAcl', $policy['Statement'][1]['Action'] );
		$this->assertCount( 2, $policy['Statement'] );

		// with bucket and CloudFront distribution.
		$policy = json_decode( $platform->get_iam_policy( 'my-bucket', 'E2QWRUHAPOMQZL' ), true );
		$this->assertSame( 'arn:aws:s3:::my-bucket', $policy['Statement'][0]['Resource'] );
		$this->assertSame( 'arn:aws:cloudfront::*:distribution/E2QWRUHAPOMQZL', $policy['Statement'][2]['Resource'] );

		// the policy could be changed.
		add_filter(
			'efmlawss3_iam_policy',
			static function ( array $policy ): array {
				$policy['Statement'][] = array( 'Sid' => 'Custom' );
				return $policy;
			}
		);
		$policy = json_decode( $platform->get_iam_policy( 'my-bucket' ), true );
		$this->assertSame( 'Custom', $policy['Statement'][2]['Sid'] );
	}

	/**
	 * Test the policy in the help of AWS S3 with the bucket of the fields.
	 *
	 * @return void
	 */
	public function test_guide_with_bucket(): void {
		$platform = AwsS3::get_instance();
		$this->set_platform_fields(
			$platform,
			array(
				'bucket'                     => 'guide-bucket',
				'cloudfront_distribution_id' => 'EDISTRIBUTION',
			)
		);
		$guide = $platform->get_setup_guide();
		$this->assertStringContainsString( 'arn:aws:s3:::guide-bucket/*', $guide['code']['content'] );
		$this->assertStringContainsString( 'cloudfront:CreateInvalidation', $guide['code']['content'] );
		$this->assertStringNotContainsString( 'YOUR-BUCKET', $guide['code']['hint'] );
	}

	/**
	 * Test the hint with the missing permission in the error text.
	 *
	 * @return void
	 */
	public function test_missing_permission(): void {
		$platform = AwsS3::get_instance();
		$this->assertSame( 's3:PutObject', $platform->get_missing_permission( 'User: arn:aws:iam::123:user/web is not authorized to perform: s3:PutObject on resource: "arn:aws:s3:::b/x" because no identity-based policy allows the s3:PutObject action' ) );
		$this->assertSame( 'cloudfront:CreateInvalidation', $platform->get_missing_permission( 'is not authorized to perform cloudfront:CreateInvalidation on resource' ) );
		$this->assertSame( '', $platform->get_missing_permission( 'Access Denied' ) );

		// the text for an exception names the permission.
		$exception = new AwsException(
			'Error executing "PutObject"',
			new Command( 'PutObject' ),
			array(
				'code'     => 'AccessDenied',
				'message'  => 'User: arn:aws:iam::123:user/web is not authorized to perform: s3:PutObject on resource: "arn:aws:s3:::b/x"',
				'response' => new \GuzzleHttp\Psr7\Response( 403 ),
			)
		);
		$text = $platform->get_error_text_for_exception( $exception );
		$this->assertStringContainsString( 'missing the permission s3:PutObject', $text );

		// without permission in the message, the general hint is used.
		$exception = new AwsException(
			'Error executing "PutObject"',
			new Command( 'PutObject' ),
			array(
				'code'     => 'AccessDenied',
				'message'  => 'Access Denied',
				'response' => new \GuzzleHttp\Psr7\Response( 403 ),
			)
		);
		$this->assertStringContainsString( 'Check its permissions for the bucket.', $platform->get_error_text_for_exception( $exception ) );
	}
}
