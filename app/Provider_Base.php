<?php
/**
 * File to handle the base object for each S3-provider.
 *
 * @package external-files-from-aws-s3
 */

namespace ExternalFilesFromAwsS3;

// prevent direct access.
defined( 'ABSPATH' ) || exit;


/**
 * Base object for each S3-provider.
 */
class Provider_Base {
	/**
	 * Set the internal name.
	 *
	 * @var string
	 */
	protected string $name = '';

	/**
	 * Return the name of the object.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return $this->name;
	}

	/**
	 * Return the public label.
	 *
	 * @return string
	 */
	public function get_label(): string {
		return '';
	}

	/**
	 * Initialize the provider.
	 *
	 * @return void
	 */
	public function init(): void {}

	/**
	 * Return the public URL of a file.
	 *
	 * @param string $key The given file key.
	 * @param array<string,mixed> $fields List of fields.
	 *
	 * @return string
	 */
	public function get_public_url_of_file( string $key, array $fields ): string {
		return '';
	}
}
