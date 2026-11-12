<?php
/**
 * Tests for the usage of constants for the credentials of the platforms.
 *
 * @package external-files-from-aws-s3
 */

namespace ExternalFilesFromAwsS3\Tests\Unit\Platforms;

use ExternalFilesFromAwsS3\Platform_Base;
use ExternalFilesFromAwsS3\Platforms\AwsS3;
use ExternalFilesFromAwsS3\Platforms\BackblazeS3;
use ExternalFilesFromAwsS3\Platforms\CloudflareR2;
use ExternalFilesFromAwsS3\Platforms\HetznerObjectStorage;
use ExternalFilesFromAwsS3\Platforms\S3Compatible;
use ExternalFilesFromAwsS3\Tests\externalFilesFromAwsS3Tests;

/**
 * Object to test the usage of constants for the credentials of the platforms.
 *
 * The constants of PHP could not be removed after they have been defined. Therefore, each test
 * uses its own prefix for the constant names via the hook "efml_service_field_constant_name".
 */
class Constants extends externalFilesFromAwsS3Tests {
	/**
	 * The prefix for the constants of the actual test.
	 *
	 * @var string
	 */
	private string $prefix = '';

	/**
	 * Counter for unique prefixes.
	 *
	 * @var int
	 */
	private static int $counter = 0;

	/**
	 * Prepare each test.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		// bail if the main plugin does not support the checks of the target.
		if ( ! method_exists( AwsS3::get_instance(), 'is_target_blocked_by_constants' ) ) {
			$this->markTestSkipped( 'Requires "External Files in Media Library" 6.0.0 or newer.' );
		}

		// use a unique prefix for the constants of this test.
		++self::$counter;
		$this->prefix = 'EFMLAWSTEST' . self::$counter . '_';
		add_filter( 'efml_service_field_constant_name', array( $this, 'set_constant_name' ) );
	}

	/**
	 * Reset the environment after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_filter( 'efml_service_field_constant_name', array( $this, 'set_constant_name' ) );
		foreach ( array( AwsS3::get_instance(), BackblazeS3::get_instance(), CloudflareR2::get_instance(), S3Compatible::get_instance(), HetznerObjectStorage::get_instance() ) as $platform ) {
			$platform->set_fields( array() );
		}
		parent::tear_down();
	}

	/**
	 * Use the prefix of the actual test for the constant names.
	 *
	 * @param string $constant_name The name of the constant.
	 *
	 * @return string
	 */
	public function set_constant_name( string $constant_name ): string {
		return $this->prefix . $constant_name;
	}

	/**
	 * Define the given constants for the actual test.
	 *
	 * @param array<string,string> $constants The constants without prefix and their values.
	 *
	 * @return void
	 */
	private function define_constants( array $constants ): void {
		foreach ( $constants as $name => $value ) {
			define( $this->prefix . $name, $value );
		}
	}

	/**
	 * Define all constants for AWS S3.
	 *
	 * @return void
	 */
	private function define_aws_constants(): void {
		$this->define_constants(
			array(
				'EFML_AWS_S3_ACCESS_KEY' => 'constant-key',
				'EFML_AWS_S3_SECRET'     => 'constant-secret',
				'EFML_AWS_S3_BUCKET'     => 'constant-bucket',
				'EFML_AWS_S3_REGION'     => 'eu-central-1',
			)
		);
	}

	/**
	 * Return the fields of a saved external source.
	 *
	 * @param string $bucket The bucket.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function get_source_fields( string $bucket ): array {
		return array(
			'access_key' => array( 'value' => 'own-key' ),
			'secret'     => array( 'value' => 'own-secret' ),
			'bucket'     => array( 'value' => $bucket ),
			'region'     => array( 'value' => 'eu-central-1' ),
		);
	}

	/**
	 * Test the required fields of the platforms.
	 *
	 * @return void
	 */
	public function test_required_fields(): void {
		$this->assertEquals( array( 'access_key', 'secret', 'bucket', 'region' ), AwsS3::get_instance()->get_required_fields_for_constants() );
		$this->assertEquals( array( 'access_key', 'secret', 'bucket', 'region' ), BackblazeS3::get_instance()->get_required_fields_for_constants() );
		$this->assertEquals( array( 'account_id', 'access_key', 'secret', 'bucket' ), CloudflareR2::get_instance()->get_required_fields_for_constants() );
	}

	/**
	 * Test that no constant is used if not all required constants are defined.
	 *
	 * @return void
	 */
	public function test_partial_constants(): void {
		$this->define_constants(
			array(
				'EFML_AWS_S3_ACCESS_KEY' => 'constant-key',
				'EFML_AWS_S3_SECRET'     => 'constant-secret',
			)
		);

		$platform = AwsS3::get_instance();
		$platform->set_fields( $this->get_source_fields( 'my-bucket' ) );
		$this->assertEquals( 'own-key', $platform->get_fields()['access_key']['value'] );
		$this->assertFalse( $platform->is_mode_forced_by_constants() );
	}

	/**
	 * Test the constants for an external source of the same bucket: they are used.
	 *
	 * @return void
	 */
	public function test_same_bucket(): void {
		$this->define_aws_constants();

		$platform = AwsS3::get_instance();
		$platform->set_fields( $this->get_source_fields( 'constant-bucket' ) );
		$fields = $platform->get_fields();
		$this->assertEquals( 'constant-key', $fields['access_key']['value'] );
		$this->assertEquals( 'constant-secret', $fields['secret']['value'] );
		$this->assertFalse( $platform->is_target_blocked_by_constants( '', $this->get_source_fields( 'constant-bucket' ) ) );
		$this->assertTrue( $platform->is_mode_forced_by_constants() );
	}

	/**
	 * Test the constants for an external source of another bucket: they are not used and the source is blocked.
	 *
	 * @return void
	 */
	public function test_other_bucket(): void {
		$this->define_aws_constants();

		$platform = AwsS3::get_instance();
		$platform->set_fields( $this->get_source_fields( 'other-bucket' ) );
		$fields = $platform->get_fields();
		$this->assertEquals( 'own-key', $fields['access_key']['value'] );
		$this->assertEquals( 'other-bucket', $fields['bucket']['value'] );
		$this->assertTrue( $platform->is_target_blocked_by_constants( '', $this->get_source_fields( 'other-bucket' ) ) );
	}

	/**
	 * Test the constants without fields from outside (e.g. in the directory listing): they are used.
	 *
	 * @return void
	 */
	public function test_without_fields(): void {
		$this->define_aws_constants();

		$platform = AwsS3::get_instance();
		$platform->set_fields( array() );
		$this->assertEquals( 'constant-bucket', $platform->get_bucket_name() );
		$this->assertEquals( 'constant-key', $platform->get_fields()['access_key']['value'] );
	}

	/**
	 * Test the target fields of Backblaze: bucket and region.
	 *
	 * @return void
	 */
	public function test_backblaze_region(): void {
		$this->define_constants(
			array(
				'EFML_BACKBLAZE_S3_ACCESS_KEY' => 'constant-key',
				'EFML_BACKBLAZE_S3_SECRET'     => 'constant-secret',
				'EFML_BACKBLAZE_S3_BUCKET'     => 'constant-bucket',
				'EFML_BACKBLAZE_S3_REGION'     => 'us-west-004',
			)
		);

		$platform = BackblazeS3::get_instance();
		$fields   = array(
			'bucket' => array( 'value' => 'constant-bucket' ),
			'region' => array( 'value' => 'eu-central-003' ),
		);
		$this->assertInstanceOf( Platform_Base::class, $platform );
		$this->assertTrue( $platform->is_target_blocked_by_constants( '', $fields ) );
		$fields['region']['value'] = 'us-west-004';
		$this->assertFalse( $platform->is_target_blocked_by_constants( '', $fields ) );
	}

	/**
	 * Test the required fields of the S3-compatible storage and its presets.
	 *
	 * @return void
	 */
	public function test_required_fields_of_s3_compatible(): void {
		$this->assertEquals( array( 'endpoint', 'region', 'access_key', 'secret', 'bucket' ), S3Compatible::get_instance()->get_required_fields_for_constants() );
		$this->assertEquals( array( 'region', 'access_key', 'secret', 'bucket' ), HetznerObjectStorage::get_instance()->get_required_fields_for_constants() );
		$this->assertEquals( 'EFMLAWSTEST' . self::$counter . '_EFML_S3_COMPATIBLE_ENDPOINT', S3Compatible::get_instance()->get_constant_name_for_field( 'endpoint' ) );
		$this->assertEquals( 'EFMLAWSTEST' . self::$counter . '_EFML_HETZNER_OBJECT_STORAGE_REGION', HetznerObjectStorage::get_instance()->get_constant_name_for_field( 'region' ) );
	}

	/**
	 * Test the constants of the S3-compatible storage: they are bound to the endpoint and the bucket.
	 *
	 * @return void
	 */
	public function test_s3_compatible(): void {
		$this->define_constants(
			array(
				'EFML_S3_COMPATIBLE_ENDPOINT'   => 'https://s3.example.com/',
				'EFML_S3_COMPATIBLE_REGION'     => 'eu-central-1',
				'EFML_S3_COMPATIBLE_ACCESS_KEY' => 'constant-key',
				'EFML_S3_COMPATIBLE_SECRET'     => 'constant-secret',
				'EFML_S3_COMPATIBLE_BUCKET'     => 'constant-bucket',
				'EFML_S3_COMPATIBLE_PUBLIC_URL' => 'https://cdn.example.com',
			)
		);
		$platform = S3Compatible::get_instance();

		// test 1: without fields from outside the constants are used.
		$platform->set_fields( array() );
		$this->assertTrue( $platform->is_mode_forced_by_constants() );
		$this->assertEquals( 'https://s3.example.com/constant-bucket/', $platform->get_url_mark() );
		$this->assertEquals( 'constant-secret', $platform->get_fields()['secret']['value'] );
		$this->assertEquals( 'https://cdn.example.com/example.jpg', $platform->get_public_url_of_file( 'example.jpg', $platform->get_fields() ) );

		// test 2: a source for the same endpoint (in another notation) and bucket uses the constants.
		$fields = array(
			'endpoint' => array( 'value' => 'https://S3.example.com' ),
			'bucket'   => array( 'value' => 'constant-bucket' ),
		);
		$this->assertFalse( $platform->is_target_blocked_by_constants( '', $fields ) );

		// test 3: a source for another endpoint is blocked.
		$fields['endpoint']['value'] = 'https://s3.other.example.com';
		$this->assertTrue( $platform->is_target_blocked_by_constants( '', $fields ) );

		// test 4: a source for another bucket is blocked.
		$fields = array(
			'endpoint' => array( 'value' => 'https://s3.example.com' ),
			'bucket'   => array( 'value' => 'other-bucket' ),
		);
		$this->assertTrue( $platform->is_target_blocked_by_constants( '', $fields ) );
	}

	/**
	 * Test the constants of a preset: they are bound to the region and the bucket.
	 *
	 * @return void
	 */
	public function test_preset(): void {
		$this->define_constants(
			array(
				'EFML_HETZNER_OBJECT_STORAGE_REGION'     => 'nbg1',
				'EFML_HETZNER_OBJECT_STORAGE_ACCESS_KEY' => 'constant-key',
				'EFML_HETZNER_OBJECT_STORAGE_SECRET'     => 'constant-secret',
				'EFML_HETZNER_OBJECT_STORAGE_BUCKET'     => 'constant-bucket',
			)
		);
		$platform = HetznerObjectStorage::get_instance();
		$platform->set_fields( array() );
		$this->assertEquals( 'https://constant-bucket.nbg1.your-objectstorage.com/', $platform->get_url_mark() );

		$fields = array(
			'region' => array( 'value' => 'fsn1' ),
			'bucket' => array( 'value' => 'constant-bucket' ),
		);
		$this->assertTrue( $platform->is_target_blocked_by_constants( '', $fields ) );
		$fields['region']['value'] = 'nbg1';
		$this->assertFalse( $platform->is_target_blocked_by_constants( '', $fields ) );

		// the generic storage is not affected.
		$this->assertFalse( S3Compatible::get_instance()->is_mode_forced_by_constants() );
	}
}
