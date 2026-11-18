<?php
/**
 * File to handle the configuration to use only an S3-compatible storage.
 *
 * @package external-files-from-aws-s3
 */

namespace ExternalFilesFromAwsS3\Platforms\S3Compatible;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesFromAwsS3\Platforms\S3Compatible;
use ExternalFilesInMediaLibrary\Plugin\Configuration_Base;
use ExternalFilesInMediaLibrary\Services\Services;

/**
 * Object to handle the configuration to use only an S3-compatible storage.
 *
 * Presets extend this object and set their own name and platform class name.
 */
class Configuration extends Configuration_Base {
	/**
	 * The name of this configuration.
	 *
	 * @var string
	 */
	protected string $name = 's3-compatible';

	/**
	 * The class name of the platform.
	 *
	 * @var class-string<S3Compatible>
	 */
	protected string $platform_class_name = S3Compatible::class;

	/**
	 * Constructor.
	 */
	public function __construct() {}

	/**
	 * Return the platform object.
	 *
	 * @return S3Compatible
	 */
	protected function get_platform(): S3Compatible {
		return $this->platform_class_name::get_instance();
	}

	/**
	 * Return the title of this configuration.
	 *
	 * @return string
	 */
	public function get_title(): string {
		/* translators: %1$s will be replaced by the name of the platform. */
		return sprintf( __( 'Use only %1$s', 'external-files-from-aws-s3' ), $this->get_platform()->get_label() );
	}

	/**
	 * Return the hints for the dialog.
	 *
	 * @return array<int,string>
	 */
	public function get_dialog_hints(): array {
		// get the label.
		$label = $this->get_platform()->get_label();

		return array(
			/* translators: %1$s will be replaced by the name of the platform. */
			'<p>' . sprintf( __( 'This will disable all other services except %1$s.', 'external-files-from-aws-s3' ), $label ) . '<br>' . sprintf( __( 'After that, you will only be able to see and use %1$s for external sources.', 'external-files-from-aws-s3' ), $label ) . '</p>',
		);
	}

	/**
	 * Run the configuration.
	 *
	 * @return void
	 */
	public function run(): void {
		// get the platform.
		$platform = $this->get_platform();

		// loop through all services and disable them - except our own.
		foreach ( Services::get_instance()->get_services_as_objects() as $service_obj ) {
			// bail if method for the name does not exist.
			if ( ! method_exists( $service_obj, 'get_name' ) ) {
				continue;
			}

			// bail if this is our service.
			if ( $service_obj->get_name() === $platform->get_name() ) {
				update_option( 'eml_service_' . $service_obj->get_name() . '_allowed_roles', $platform->get_default_roles() );
				continue;
			}

			// remove any capability to use this service to hide it.
			update_option( 'eml_service_' . $service_obj->get_name() . '_allowed_roles', array() );
		}

		// disable hints for other plugins.
		update_option( 'eml_disable_plugin_hints', 1 );
	}
}
