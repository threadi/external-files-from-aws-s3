<?php
/**
 * This file contains the main initialization object for this plugin.
 *
 * @package external-files-from-aws-s3
 */

namespace ExternalFilesFromAwsS3\Plugin;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\Plugin\Roles;
use ExternalFilesInMediaLibrary\Services\Service_Base;
use ExternalFilesInMediaLibrary\Services\Service_Plugin_Base;

/**
 * Initialize the plugin, connect all together.
 */
class Init {

	/**
	 * Instance of actual object.
	 *
	 * @var ?Init
	 */
	private static ?Init $instance = null;

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
	 * @return Init
	 */
	public static function get_instance(): Init {
		if ( is_null( self::$instance ) ) {
			self::$instance = new static();
		}

		return self::$instance;
	}

	/**
	 * Initialize this object.
	 *
	 * @return void
	 */
	public function init(): void {
		// init update handling.
		Updates::get_instance()->init();

		// init the connection test for S3-compatible storages.
		Connection_Test::get_instance()->init();

		// plugin-action.
		register_activation_hook( EFMLAWSS3_PLUGIN, array( $this, 'activation' ) );

		// add the service.
		add_filter( 'efml_services_support', array( $this, 'add_service' ) );
		add_filter( 'efml_service_plugins', array( $this, 'remove_service_plugin' ) );
		add_filter( 'efml_configurations', array( $this, 'add_configurations' ) );
		add_action( 'admin_init', array( $this, 'add_missing_capabilities' ) );

		// misc.
		add_action( 'init', array( $this, 'init_languages' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'add_styles_in_admin' ) );
		add_filter( 'wp_consent_api_registered_' . plugin_basename( EFMLAWSS3_PLUGIN ), array( $this, 'register_consent_api' ) );
	}

	/**
	 * Add the support for languages.
	 *
	 * @return void
	 */
	public function init_languages(): void {
		// load language files for pro.
		load_plugin_textdomain( 'external-files-from-aws-s3', false, dirname( plugin_basename( EFMLAWSS3_PLUGIN ) ) . '/languages' );
	}

	/**
	 * Return the supported S3-compatible platforms.
	 *
	 * @return array<int,string>
	 */
	private function get_platforms(): array {
		return array(
			'ExternalFilesFromAwsS3\Platforms\AwsS3',
			'ExternalFilesFromAwsS3\Platforms\BackblazeS3',
			'ExternalFilesFromAwsS3\Platforms\CloudflareR2',
			'ExternalFilesFromAwsS3\Platforms\DigitalOceanSpaces',
			'ExternalFilesFromAwsS3\Platforms\S3Compatible',
			'ExternalFilesFromAwsS3\Platforms\HetznerObjectStorage',
			'ExternalFilesFromAwsS3\Platforms\IonosObjectStorage',
			'ExternalFilesFromAwsS3\Platforms\OvhcloudObjectStorage',
			'ExternalFilesFromAwsS3\Platforms\ScalewayObjectStorage',
			'ExternalFilesFromAwsS3\Platforms\Wasabi',
		);
	}

	/**
	 * Return the supported S3-compatible platforms as objects.
	 *
	 * @return array<int,Service_Base>
	 */
	private function get_platforms_as_object(): array {
		// create the list.
		$list = array();

		// add each supported platform.
		foreach ( $this->get_platforms() as $platform_class_name ) {
			// bail if class does not exist.
			if ( ! class_exists( $platform_class_name ) ) {
				continue;
			}

			// get class name with method.
			$class_name = $platform_class_name . '::get_instance';

			// bail if it is not callable.
			if ( ! is_callable( $class_name ) ) {
				continue;
			}

			// initiate object.
			$obj = $class_name();

			// bail if object is not a "Service_Base" object.
			if ( ! $obj instanceof Service_Base ) {
				continue;
			}

			// add it to the list.
			$list[] = $obj;
		}

		// return the resulting list.
		return $list;
	}

	/**
	 * Add the services for the supported platforms to the main plugin.
	 *
	 * @param array<int,string> $services The list of services.
	 *
	 * @return array<int,string>
	 */
	public function add_service( array $services ): array {
		return array_merge( $services, $this->get_platforms() );
	}

	/**
	 * Run during plugin activation.
	 *
	 * @return void
	 */
	public function activation(): void {
		// get the roles object.
		$roles_obj = Roles::get_instance();

		// add each supported platform.
		foreach ( $this->get_platforms_as_object() as $obj ) {
			$roles_obj->set( $obj->get_default_roles(), 'efml_cap_' . $obj->get_name() );
		}
	}

	/**
	 * Add the capabilities for platforms, which have been added with an update of this plugin.
	 *
	 * The capabilities are set during the activation of this plugin, but not during an update.
	 * They are only set for platforms, whose capability is not used by any role and whose permissions
	 * have never been saved, so changes of the permissions are never overwritten. Each platform is checked only once.
	 *
	 * @return void
	 */
	public function add_missing_capabilities(): void {
		// get the list of already checked platforms.
		$checked = get_option( 'efmlawss3_platforms_with_capabilities', array() );
		if ( ! is_array( $checked ) ) {
			$checked = array();
		}

		// check each platform.
		$changed = false;
		foreach ( $this->get_platforms_as_object() as $obj ) {
			// bail if this platform has already been checked.
			if ( in_array( $obj->get_name(), $checked, true ) ) {
				continue;
			}

			// mark the platform as checked.
			$checked[] = $obj->get_name();
			$changed   = true;

			// bail if the permissions of this platform has been saved.
			if ( false !== get_option( 'eml_service_' . $obj->get_name() . '_allowed_roles' ) ) {
				continue;
			}

			// bail if any role has the capability.
			if ( $this->is_capability_used( 'efml_cap_' . $obj->get_name() ) ) {
				continue;
			}

			// set the capability for the default roles.
			Roles::get_instance()->set( $obj->get_default_roles(), 'efml_cap_' . $obj->get_name() );
		}

		// bail if nothing has been changed.
		if ( ! $changed ) {
			return;
		}

		// save the list of checked platforms.
		update_option( 'efmlawss3_platforms_with_capabilities', $checked );
	}

	/**
	 * Return whether any role has the given capability.
	 *
	 * @param string $capability The capability.
	 *
	 * @return bool
	 */
	private function is_capability_used( string $capability ): bool {
		foreach ( wp_roles()->role_objects as $role_obj ) {
			if ( $role_obj->has_cap( $capability ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Check if External files in media library is active:
	 * 1. in the actual blog.
	 * 2. in the global network, if multisite is used.
	 *
	 * @return bool
	 */
	public function is_parent_plugin_active(): bool {
		// set the slug.
		$slug = 'external-files-in-media-library/external-files-in-media-library.php';

		// check the actual blog.
		$is_active = in_array( $slug, (array) get_option( 'active_plugins', array() ), true );

		// bail if result is true.
		if ( $is_active ) {
			return true;
		}

		// bail if we are not in multisite.
		if ( ! is_multisite() ) {
			return false;
		}

		// get sitewide plugins.
		$sitewide_plugins = get_site_option( 'active_sitewide_plugins' );

		// bail if not list could be loaded.
		if ( ! is_array( $sitewide_plugins ) ) {
			return false;
		}

		// return the result.
		return isset( $sitewide_plugins[ $slug ] );
	}

	/**
	 * Remove the service plugin from the main plugin.
	 *
	 * @param array<string,Service_Plugin_Base> $plugins List of plugins.
	 * @return array<string,Service_Plugin_Base>
	 */
	public function remove_service_plugin( array $plugins ): array {
		unset( $plugins['external-files-from-aws-s3'] );
		return $plugins;
	}

	/**
	 * Add our own custom configuration to the list.
	 *
	 * @param array<int,string> $configurations List of configurations.
	 *
	 * @return array<int,string>
	 */
	public function add_configurations( array $configurations ): array {
		foreach ( $this->get_platforms_as_object() as $obj ) {
			$configurations[] = $obj->get_configuration_object_name();
		}

		// return the resulting configurations.
		return $configurations;
	}

	/**
	 * We simply return true to register the plugin with WP Consent API, although we do not use it
	 * as this plugin does not set any cookies or collect any personal data.
	 *
	 * @return bool
	 */
	public function register_consent_api(): bool {
		return true;
	}

	/**
	 * Add CSS- and JS-files for the backend.
	 *
	 * @param string $hook The used hook.
	 *
	 * @return void
	 */
	public function add_styles_in_admin( string $hook ): void {
		// bail if page is used where we do not use it.
		// TODO find better way.
		if ( ! in_array(
			$hook,
			array(
				'plugins_page_efml_service_plugins',
				'upload.php',
				'media-new.php',
				'edit-tags.php',
				'post.php',
				'settings_page_eml_settings',
				'options-general.php',
				'media_page_efml_local_directories',
				'term.php',
				'profile.php',
			),
			true
		) ) {
			return;
		}

		// admin-specific styles.
		wp_enqueue_style(
			'efmlawss3-admin',
			Helper::get_plugin_url() . 'admin/style.css',
			array(),
			Helper::get_file_version( Helper::get_plugin_dir() . 'admin/style.css' ),
		);
	}
}
