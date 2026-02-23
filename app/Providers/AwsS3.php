<?php
/**
 * File to handle the base options for Amazon owns AWS S3.
 *
 * @package external-files-from-aws-s3
 */

namespace ExternalFilesFromAwsS3\Providers;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesFromAwsS3\Provider_Base;

/**
 * Object for export files to AWS S3.
 */
class AwsS3 extends Provider_Base {
	/**
	 * Set the internal name.
	 *
	 * @var string
	 */
	protected string $name = 'aws_s3';

	/**
	 * Instance of actual object.
	 *
	 * @var ?AwsS3
	 */
	private static ?AwsS3 $instance = null;

	/**
	 * Constructor, not used as this a Singleton object.
	 */
	private function __construct() {}

	/**
	 * Prevent cloning of this object.
	 *
	 * @return void
	 */
	private function __clone() {}

	/**
	 * Return instance of this object as singleton.
	 *
	 * @return AwsS3
	 */
	public static function get_instance(): AwsS3 {
		if ( is_null( self::$instance ) ) {
			self::$instance = new static();
		}

		return self::$instance;
	}

	/**
	 * Return the public label.
	 *
	 * @return string
	 */
	public function get_label(): string {
		return 'AWS S3';
	}

	/**
	 * Return the public URL of a file.
	 *
	 * @param string $key The given file key.
	 * @param array<string,mixed> $fields List of fields.
	 *
	 * @return string
	 */
	public function get_public_url_of_file( string $key, array $fields ): string {
		return sprintf(
			'https://%s.s3.%s.amazonaws.com/%s',
			$fields['bucket']['value'],
			$fields['region']['value'],
			$key
		);
	}
}
