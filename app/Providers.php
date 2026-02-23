<?php
/**
 * File as base for each S3-compatible providers we support.
 *
 * @package external-files-from-aws-s3
 */

namespace ExternalFilesFromAwsS3;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Object to handle support for AWS S3-based directory listing.
 */
class Providers {
	/**
	 * Instance of actual object.
	 *
	 * @var ?Providers
	 */
	private static ?Providers $instance = null;

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
	 * @return Providers
	 */
	public static function get_instance(): Providers {
		if ( is_null( self::$instance ) ) {
			self::$instance = new static();
		}

		return self::$instance;
	}

	/**
	 * Return list of available S3-compatible providers.
	 *
	 * @return array<int,string>
	 */
	private function get_providers(): array {
		$list = array(
			'ExternalFilesFromAwsS3\Providers\AwsS3',
			'ExternalFilesFromAwsS3\Providers\CloudflareR2',
		);

		/**
		 * Filter the list of available S3-compatible providers we support.
		 *
		 * @since 1.0.0 Available since 1.0.0.
		 * @param array<int,string> $list List of providers.
		 */
		return apply_filters( 'efmlawss3_aws_providers', $list );
	}

	/**
	 * Return list of providers as objects.
	 *
	 * @return array<int,Provider_Base>
	 */
	public function get_providers_as_objects(): array {
		// create the list.
		$providers = array();

		// add the providers to the list.
		foreach ( $this->get_providers() as $provider_class_name ) {
			// bail if class does not exist.
			if ( ! class_exists( $provider_class_name ) ) {
				continue;
			}

			// get class name with method.
			$class_name = $provider_class_name . '::get_instance';

			// bail if it is not callable.
			if ( ! is_callable( $class_name ) ) {
				continue;
			}

			// initiate object.
			$obj = $class_name();

			// bail if object is not a service object.
			if ( ! $obj instanceof Provider_Base ) {
				continue;
			}

			// add the object to the list.
			$providers[] = $obj;
		}

		// return the resulting list.
		return $providers;
	}

	/**
	 * Return a provider by a given name.
	 *
	 * @param string $name The name of the provider.
	 *
	 * @return Provider_Base|false
	 */
	public function get_provider_by_name( string $name ): Provider_Base|false {
		foreach( $this->get_providers_as_objects() as $provider_obj ) {
			// bail if name does not match.
			if( $name !== $provider_obj->get_name() ) {
				continue;
			}

			// return this object.
			return $provider_obj;
		}

		// return false as no provider could be found.
		return false;
	}
}
