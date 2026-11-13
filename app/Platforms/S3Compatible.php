<?php
/**
 * File to handle the support for any S3-compatible storage with an own endpoint.
 *
 * @package external-files-from-aws-s3
 */

namespace ExternalFilesFromAwsS3\Platforms;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use Aws\Exception\AwsException;
use Aws\S3\S3Client;
use easyDirectoryListingForWordPress\Crypt;
use easySettingsForWordPress\Fields\Checkbox;
use easySettingsForWordPress\Fields\Password;
use easySettingsForWordPress\Fields\Select;
use easySettingsForWordPress\Fields\Text;
use easySettingsForWordPress\Fields\TextInfo;
use easySettingsForWordPress\Page;
use easySettingsForWordPress\Section;
use easySettingsForWordPress\Tab;
use Exception;
use ExternalFilesFromAwsS3\Platform_Base;
use ExternalFilesInMediaLibrary\ExternalFiles\Export_Base;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Log;
use ExternalFilesInMediaLibrary\Plugin\Settings;
use ExternalFilesInMediaLibrary\Services\Service;
use WP_Error;
use WP_User;

/**
 * Object to handle support for any S3-compatible storage with an own endpoint, e.g. MinIO, Garage or Ceph.
 *
 * This object is also the base for the presets of S3-compatible providers (see "S3Compatible_Preset"),
 * which only define the regions and endpoints of the provider.
 */
class S3Compatible extends Platform_Base implements Service {
	/**
	 * The object name.
	 *
	 * @var string
	 */
	protected string $name = 's3-compatible';

	/**
	 * List of fields, which identify the bucket of this platform (endpoint and bucket).
	 *
	 * @var array<int,string>
	 */
	protected array $constants_target_fields = array( 'endpoint', 'bucket' );

	/**
	 * The public label.
	 *
	 * @var string
	 */
	protected string $label = 'S3-compatible storage';

	/**
	 * The class name of the protocol class.
	 *
	 * @var string
	 */
	protected string $protocol_class_name = 'ExternalFilesFromAwsS3\Platforms\S3Compatible\Protocol';

	/**
	 * Name of the configuration object.
	 *
	 * @var string
	 */
	protected string $configuration_object_name = 'ExternalFilesFromAwsS3\Platforms\S3Compatible\Configuration';

	/**
	 * The class name of the export object.
	 *
	 * @var string
	 */
	protected string $export_class_name = 'ExternalFilesFromAwsS3\Platforms\S3Compatible\Export';

	/**
	 * Slug of settings tab.
	 *
	 * @var string
	 */
	protected string $settings_sub_tab = 'eml_s3_compatible';

	/**
	 * Instance of actual object.
	 *
	 * Each preset must declare this property, too, to get its own instance.
	 *
	 * @var ?S3Compatible
	 */
	protected static ?S3Compatible $instance = null;

	/**
	 * Constructor, not used as this a Singleton object.
	 */
	protected function __construct() {}

	/**
	 * Prevent cloning of this object.
	 *
	 * @return void
	 */
	private function __clone() {}

	/**
	 * Return instance of this object as singleton.
	 *
	 * @return static
	 */
	public static function get_instance(): static {
		if ( ! static::$instance instanceof static ) {
			static::$instance = new static();
		}

		return static::$instance;
	}

	/**
	 * Run during activation of the plugin.
	 *
	 * @return void
	 */
	public function activation(): void {}

	/**
	 * Return the public label.
	 *
	 * @return string
	 */
	public function get_label(): string {
		// return the translated label for the generic storage.
		if ( self::class === static::class ) {
			return __( 'S3-compatible storage', 'external-files-from-aws-s3' );
		}

		// return the label of the provider.
		return $this->label;
	}

	/**
	 * Initialize this object.
	 *
	 * @return void
	 */
	public function init(): void {
		// use parent initialization.
		parent::init();

		// add settings.
		add_action( 'init', array( $this, 'init_settings' ), 30 );

		// bail if user has no capability for this service.
		if ( ! defined( 'DOING_CRON' ) && ! Helper::is_cli() && ! current_user_can( 'efml_cap_' . $this->get_name() ) ) {
			return;
		}

		// set title.
		/* translators: %1$s will be replaced by the name of the platform. */
		$this->title = sprintf( __( 'Choose file(s) from your %1$s bucket', 'external-files-from-aws-s3' ), $this->get_label() ); // @phpstan-ignore property.notFound

		// use our own hooks.
		add_filter( 'efml_http_check_content_type', array( $this, 'allow_content_type' ), 10, 2 );
		add_filter( 'efml_files_check_content_type', array( $this, 'allow_content_type' ), 10, 2 );
		add_filter( 'efml_external_file_infos', array( $this, 'set_real_mime_type' ), 10, 2 );
	}

	/**
	 * Return the slug of this platform for the names of options.
	 *
	 * @return string
	 */
	protected function get_option_slug(): string {
		return str_replace( '-', '_', $this->get_name() );
	}

	/**
	 * Return the name of the option for the given field.
	 *
	 * @param string $field_name The name of the field.
	 *
	 * @return string
	 */
	public function get_option_name( string $field_name ): string {
		return 'eml_' . $this->get_option_slug() . '_' . $field_name;
	}

	/**
	 * Return the name of the user setting (and its user meta without the prefix "efml_") for the given field.
	 *
	 * The name is the name of the platform and the name of the field, so a constant for this field
	 * could be shown in the user profile.
	 *
	 * @param string $field_name The name of the field.
	 *
	 * @return string
	 */
	public function get_user_setting_name( string $field_name ): string {
		return $this->get_name() . '_' . $field_name;
	}

	/**
	 * Return the regions of this platform with their endpoints.
	 *
	 * The generic storage has no fixed regions, the region and the endpoint are entered by the user.
	 * Presets return a list with the region as key and an array with the "label" and the "endpoint".
	 *
	 * @return array<string,array<string,string>>
	 */
	public function get_regions(): array {
		return array();
	}

	/**
	 * Return whether this platform uses a fixed list of regions with their endpoints.
	 *
	 * @return bool
	 */
	public function has_fixed_regions(): bool {
		return ! empty( $this->get_regions() );
	}

	/**
	 * Return the default region.
	 *
	 * @return string
	 */
	public function get_default_region(): string {
		// get the regions.
		$regions = $this->get_regions();

		// use the first region of the list, if set.
		if ( ! empty( $regions ) ) {
			return (string) array_key_first( $regions );
		}

		// return the default region of the S3 API.
		return 'us-east-1';
	}

	/**
	 * Return the URL of the documentation of this platform.
	 *
	 * @return string
	 */
	public function get_documentation_url(): string {
		return 'https://github.com/threadi/external-files-from-aws-s3/blob/master/docs/s3-compatible.md';
	}

	/**
	 * Return the actions.
	 *
	 * @return array<int,array<string,string>>
	 */
	public function get_actions(): array {
		// get list of allowed mime types.
		$mimetypes = implode( ',', Helper::get_allowed_mime_types() );

		return array(
			array(
				'action' => 'efml_get_import_dialog( { "service": "' . $this->get_name() . '", "urls": file.file, "fields": config.fields, "term": term } );',
				'label'  => __( 'Import', 'external-files-from-aws-s3' ),
				'show'   => 'let mimetypes = "' . $mimetypes . '";mimetypes.includes( file["mime-type"] )',
				'hint'   => '<span class="dashicons dashicons-editor-help" title="' . esc_attr__( 'File-type is not supported', 'external-files-from-aws-s3' ) . '"></span>',
			),
		);
	}

	/**
	 * Return global actions.
	 *
	 * @return array<int,array<string,string>>
	 */
	protected function get_global_actions(): array {
		return array_merge(
			parent::get_global_actions(),
			array(
				array(
					'action' => 'efml_get_import_dialog( { "service": "' . $this->get_name() . '", "urls": "' . $this->get_url_mark() . '" + actualDirectoryPath, "fields": config.fields, "term": config.term } );',
					'label'  => __( 'Import active directory', 'external-files-from-aws-s3' ),
				),
				array(
					'action' => 'efml_save_as_directory( "' . $this->get_name() . '", actualDirectoryPath, config.fields, config.term );',
					'label'  => __( 'Save active directory as your external source', 'external-files-from-aws-s3' ),
				),
			)
		);
	}

	/**
	 * Check if login with given credentials is valid.
	 *
	 * @param string $directory The directory to check.
	 *
	 * @return bool
	 */
	public function do_login( string $directory ): bool {
		// bail if credentials are missing.
		if ( empty( $this->get_fields() ) || '' === $this->get_endpoint( $this->get_fields() ) ) {
			// create error object.
			$error = new WP_Error();
			/* translators: %1$s will be replaced by the name of the platform. */
			$error->add( 'efml_service_s3_compatible', sprintf( __( 'No credentials set for this %1$s connection!', 'external-files-from-aws-s3' ), $this->get_label() ) );

			// add it to the list.
			$this->add_error( $error );

			// return false to login check.
			return false;
		}

		// get S3 client to check if credentials are ok (errors are logged by the client).
		$s3 = $this->get_s3_client();
		try {
			// try to load the requested bucket.
			$s3->listObjectsV2(
				array(
					'Bucket'  => $this->get_bucket_name(),
					'MaxKeys' => 1,
				)
			);

			// return true if it could be loaded.
			return true;
		} catch ( AwsException $e ) {
			// create error object.
			$error = new WP_Error();
			$error->add( 'efml_service_s3_compatible', $this->get_error_text_for_exception( $e ) );

			// add it to the list.
			$this->add_error( $error );

			// return false to prevent any further actions.
			return false;
		}
	}

	/**
	 * Return a hint for the given HTTP status and S3 error code.
	 *
	 * @param int    $status     The HTTP status.
	 * @param string $error_code The S3 error code.
	 *
	 * @return string
	 */
	public function get_hint_for_error( int $status, string $error_code ): string {
		// the endpoint could not be reached.
		if ( 0 === $status ) {
			return __( 'Check the endpoint URL and whether your hosting is allowed to connect to it.', 'external-files-from-aws-s3' );
		}

		// check the error codes.
		switch ( $error_code ) {
			case 'InvalidAccessKeyId':
				return __( 'Check the access key.', 'external-files-from-aws-s3' );
			case 'SignatureDoesNotMatch':
				return __( 'Check the secret key and the region.', 'external-files-from-aws-s3' );
			case 'AuthorizationHeaderMalformed':
			case 'PermanentRedirect':
			case 'IllegalLocationConstraintException':
				return __( 'Check the region of the bucket.', 'external-files-from-aws-s3' );
			case 'NoSuchBucket':
				return __( 'Check the name of the bucket, the region and the setting for path-style URLs.', 'external-files-from-aws-s3' );
			case 'AccessDenied':
				return __( 'The access key is not allowed to do this. Check its permissions for the bucket.', 'external-files-from-aws-s3' );
			case 'InvalidArgument':
			case 'NotImplemented':
			case 'XAmzContentSHA256Mismatch':
			case 'BadDigest':
				return __( 'The storage does not support a part of the request. Try the option to send checksums only if required.', 'external-files-from-aws-s3' );
		}

		// check some HTTP status without error code.
		if ( 301 === $status || 400 === $status ) {
			return __( 'Check the region of the bucket and the setting for path-style URLs.', 'external-files-from-aws-s3' );
		}
		if ( 403 === $status ) {
			return __( 'Check the credentials and the permissions of the access key.', 'external-files-from-aws-s3' );
		}
		if ( 404 === $status ) {
			return __( 'Check the name of the bucket and the setting for path-style URLs.', 'external-files-from-aws-s3' );
		}

		// return a general hint.
		return __( 'Please contact the provider of your storage with the details from the log.', 'external-files-from-aws-s3' );
	}

	/**
	 * Enable WP CLI for tasks.
	 *
	 * @return void
	 */
	public function cli(): void {}

	/**
	 * Return the directory to use.
	 *
	 * @return string
	 */
	public function get_directory(): string {
		// get the URL mark.
		$url_mark = $this->get_url_mark();

		// bail if no bucket or endpoint is set.
		if ( empty( $this->get_bucket_name() ) || '' === $url_mark ) {
			return '/';
		}

		// return the URL with bucket.
		return $url_mark;
	}

	/**
	 * Add settings.
	 *
	 * @return void
	 */
	public function init_settings(): void {
		// bail if user has no capability for this service.
		if ( ! Helper::is_cli() && ! current_user_can( 'efml_cap_' . $this->get_name() ) ) {
			return;
		}

		// bail if settings object is missing.
		if ( ! class_exists( '\easySettingsForWordPress\Settings' ) ) {
			return;
		}

		// get the settings object.
		$settings_obj = Settings::get_instance()->get_settings_obj();

		// get the settings page.
		$settings_page = $settings_obj->get_page( \ExternalFilesInMediaLibrary\Plugin\Settings::get_instance()->get_menu_slug() );

		// bail if page does not exist.
		if ( ! $settings_page instanceof Page ) {
			return;
		}

		// get tab for services.
		$services_tab = $settings_page->get_tab( $this->get_settings_tab_slug() );

		// bail if tab does not exist.
		if ( ! $services_tab instanceof Tab ) {
			return;
		}

		// get the tab for the settings.
		$tab = $services_tab->get_tab( $this->get_settings_subtab_slug() );

		// bail if tab does not exist.
		if ( ! $tab instanceof Tab ) {
			return;
		}

		// get the section.
		$section = $tab->get_section( 'section_' . $this->get_name() . '_main' );

		// bail if section does not exist.
		if ( ! $section instanceof Section ) {
			return;
		}

		// add the settings for the global mode.
		if ( defined( 'EFML_ACTIVATION_RUNNING' ) || 'global' === get_option( 'eml_' . $this->get_name() . '_credentials_vault' ) ) {
			foreach ( $this->get_field_definitions() as $field_name => $definition ) {
				// show hint if the value is defined via constant, otherwise the setting.
				if ( $this->show_constant_hint( $section, $field_name, $definition['label'] ) ) {
					continue;
				}

				// add the setting.
				$setting = $settings_obj->add_setting( $this->get_option_name( $field_name ) );
				$setting->set_section( $section );
				$setting->set_autoload( false );

				// configure the setting depending on the type of the field.
				switch ( $definition['type'] ) {
					case 'checkbox':
						$setting->set_type( 'integer' );
						$setting->set_default( $definition['default'] ? 1 : 0 );
						$field = new Checkbox( $settings_obj );
						break;
					case 'select':
						$setting->set_type( 'string' );
						$setting->set_default( $definition['default'] );
						$field = new Select( $settings_obj );
						$field->set_options( $this->get_regions_for_settings() );
						break;
					case 'password':
						$setting->set_type( 'string' );
						$setting->set_default( '' );
						$setting->set_read_callback( array( $this, 'decrypt_value' ) );
						$setting->set_save_callback( array( $this, 'encrypt_value' ) );
						$field = new Password( $settings_obj );
						$field->set_placeholder( $definition['placeholder'] );
						break;
					default:
						$setting->set_type( 'string' );
						$setting->set_default( $definition['default'] );
						if ( $definition['credential'] ) {
							$setting->set_read_callback( array( $this, 'decrypt_value' ) );
							$setting->set_save_callback( array( $this, 'encrypt_value' ) );
						}
						$field = new Text( $settings_obj );
						$field->set_placeholder( $definition['placeholder'] );
				}

				// set title and description.
				$field->set_title( $definition['label'] );
				if ( '' !== $definition['description'] ) {
					$field->set_description( $definition['description'] );
				}
				$setting->set_field( $field );
			}
		}

		// show hint for user settings.
		if ( 'user' === get_option( 'eml_' . $this->get_name() . '_credentials_vault' ) ) {
			$setting = $settings_obj->add_setting( $this->get_option_name( 'credential_location_hint' ) );
			$setting->set_section( $section );
			$setting->set_show_in_rest( false );
			$setting->prevent_export( true );
			$field = new TextInfo( $settings_obj );
			$field->set_title( __( 'Hint', 'external-files-from-aws-s3' ) );
			/* translators: %1$s will be replaced by a URL. */
			$field->set_description( sprintf( __( 'Each user will find its settings in his own <a href="%1$s">user profile</a>.', 'external-files-from-aws-s3' ), $this->get_config_url() ) );
			$setting->set_field( $field );
		}
	}

	/**
	 * Return the definitions of the fields of this platform.
	 *
	 * The order is the order in forms and settings.
	 *
	 * @return array<string,array{type: string, label: string, placeholder: string, description: string, default: string|bool, credential: bool, required: bool}>
	 */
	public function get_field_definitions(): array {
		// collect the definitions.
		$definitions = array();

		// add the endpoint, if this platform has no fixed regions.
		if ( ! $this->has_fixed_regions() ) {
			$definitions['endpoint'] = array(
				'type'        => 'text',
				'label'       => __( 'Endpoint URL', 'external-files-from-aws-s3' ),
				'placeholder' => 'https://s3.example.com',
				'description' => __( 'The URL of the S3 API of your storage provider, e.g. https://s3.example.com.', 'external-files-from-aws-s3' ),
				'default'     => '',
				'credential'  => false,
				'required'    => true,
			);
		}

		// add the region.
		$definitions['region'] = array(
			'type'        => $this->has_fixed_regions() ? 'select' : 'text',
			'label'       => __( 'Region', 'external-files-from-aws-s3' ),
			'placeholder' => 'us-east-1',
			'description' => $this->has_fixed_regions() ? '' : __( 'The region of your bucket. Use "us-east-1", if your provider does not use regions.', 'external-files-from-aws-s3' ),
			'default'     => $this->get_default_region(),
			'credential'  => false,
			'required'    => true,
		);

		// add the credentials and the bucket.
		$definitions['access_key'] = array(
			'type'        => 'text',
			'label'       => __( 'Access key', 'external-files-from-aws-s3' ),
			'placeholder' => __( 'The access key', 'external-files-from-aws-s3' ),
			'description' => '',
			'default'     => '',
			'credential'  => true,
			'required'    => true,
		);
		$definitions['secret']     = array(
			'type'        => 'password',
			'label'       => __( 'Secret key', 'external-files-from-aws-s3' ),
			'placeholder' => __( 'The secret key', 'external-files-from-aws-s3' ),
			'description' => '',
			'default'     => '',
			'credential'  => true,
			'required'    => true,
		);
		$definitions['bucket']     = array(
			'type'        => 'text',
			'label'       => __( 'Bucket', 'external-files-from-aws-s3' ),
			'placeholder' => __( 'The bucket you want to use', 'external-files-from-aws-s3' ),
			'description' => '',
			'default'     => '',
			'credential'  => false,
			'required'    => true,
		);

		// add the setting for path-style URLs, if this platform has no fixed regions.
		if ( ! $this->has_fixed_regions() ) {
			$definitions['path_style'] = array(
				'type'        => 'checkbox',
				'label'       => __( 'Use path-style URLs', 'external-files-from-aws-s3' ),
				'placeholder' => '',
				'description' => __( 'If enabled, the bucket is part of the path (https://s3.example.com/bucket/file.jpg), otherwise of the hostname (https://bucket.s3.example.com/file.jpg). Most self-hosted storages like MinIO need path-style URLs.', 'external-files-from-aws-s3' ),
				'default'     => true,
				'credential'  => false,
				'required'    => false,
			);
		}

		// add the public URL.
		$definitions['public_url'] = array(
			'type'        => 'text',
			'label'       => __( 'Public URL (optional)', 'external-files-from-aws-s3' ),
			'placeholder' => 'https://cdn.example.com',
			'description' => __( 'The URL under which the files of your bucket are public available, e.g. your own domain or a CDN. If empty, the URL of the bucket is used.', 'external-files-from-aws-s3' ),
			'default'     => '',
			'credential'  => false,
			'required'    => false,
		);

		// add the setting for checksums.
		$definitions['checksum_when_required'] = array(
			'type'        => 'checkbox',
			'label'       => __( 'Send checksums only if required', 'external-files-from-aws-s3' ),
			'placeholder' => '',
			'description' => __( 'Enable this only if uploads fail with an error like "InvalidArgument" or "NotImplemented" and your provider does not support the checksums the AWS SDK sends by default.', 'external-files-from-aws-s3' ),
			'default'     => false,
			'credential'  => false,
			'required'    => false,
		);

		// return the resulting list.
		return $definitions;
	}

	/**
	 * Return list of user settings.
	 *
	 * @return array<string,mixed>
	 */
	public function get_user_settings(): array {
		// create the list from the field definitions.
		$list = array();
		foreach ( $this->get_field_definitions() as $field_name => $definition ) {
			// create the setting.
			$setting = array(
				'label'       => $definition['label'],
				'field'       => $definition['type'],
				'placeholder' => $definition['placeholder'],
			);

			// add the options for selects.
			if ( 'select' === $definition['type'] ) {
				$setting['options'] = $this->get_regions_for_settings();
			}

			// add the default value.
			if ( ! is_bool( $definition['default'] ) && '' !== $definition['default'] ) {
				$setting['default'] = $definition['default'];
			}

			// add the description.
			if ( '' !== $definition['description'] ) {
				$setting['description'] = $definition['description'];
			}

			// add it to the list.
			$list[ $this->get_user_setting_name( $field_name ) ] = $setting;
		}

		$instance = $this;
		/**
		 * Filter the list of possible user settings for an S3-compatible storage or one of its presets.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 * @param array<string,mixed> $list The list of settings.
		 * @param S3Compatible $instance The platform object.
		 */
		return apply_filters( 'efmlawss3_s3_compatible_user_settings', $list, $instance );
	}

	/**
	 * Return the URL mark, which identifies the URLs of this bucket within this plugin.
	 *
	 * This is the URL of the bucket on the storage:
	 * - with path-style URLs: https://s3.example.com/bucket/
	 * - otherwise: https://bucket.s3.example.com/
	 *
	 * @return string
	 */
	public function get_url_mark(): string {
		return $this->get_bucket_url( $this->get_fields() );
	}

	/**
	 * Return the URL of the bucket on the storage for the given fields. Returns an empty string, if not possible.
	 *
	 * @param array<string,mixed> $fields List of fields.
	 *
	 * @return string
	 */
	public function get_bucket_url( array $fields ): string {
		// get the endpoint and the bucket.
		$endpoint = $this->get_endpoint( $fields );
		$bucket   = $this->get_field_value( $fields, 'bucket' );

		// bail if one of them is missing.
		if ( '' === $endpoint || '' === $bucket ) {
			return '';
		}

		// return the URL with the bucket in the path.
		if ( $this->is_path_style( $fields ) ) {
			return $endpoint . '/' . $bucket . '/';
		}

		// get the parts of the endpoint.
		$scheme = wp_parse_url( $endpoint, PHP_URL_SCHEME );
		$host   = wp_parse_url( $endpoint, PHP_URL_HOST );
		$port   = wp_parse_url( $endpoint, PHP_URL_PORT );

		// bail if the endpoint is not a valid URL.
		if ( ! is_string( $scheme ) || ! is_string( $host ) ) {
			return '';
		}

		// return the URL with the bucket in the hostname.
		return $scheme . '://' . $bucket . '.' . $host . ( is_int( $port ) ? ':' . $port : '' ) . '/';
	}

	/**
	 * Return the endpoint URL for the given fields, without trailing slash. Returns an empty string, if not set.
	 *
	 * @param array<string,mixed> $fields List of fields.
	 *
	 * @return string
	 */
	public function get_endpoint( array $fields ): string {
		// use the endpoint of the region, if this platform has fixed regions.
		if ( $this->has_fixed_regions() ) {
			// get the regions.
			$regions = $this->get_regions();

			// get the region.
			$region = $this->get_field_value( $fields, 'region' );

			// bail if region is unknown.
			if ( ! isset( $regions[ $region ]['endpoint'] ) ) {
				return '';
			}

			// return the endpoint of this region.
			return $this->normalize_url( $regions[ $region ]['endpoint'] );
		}

		// return the configured endpoint.
		return $this->normalize_url( $this->get_field_value( $fields, 'endpoint' ) );
	}

	/**
	 * Return whether the values of the target fields in the given fields are empty or equal to the values
	 * defined via constants for the given connection.
	 *
	 * The endpoint is compared in its normalized form, so e.g. a trailing slash does not matter.
	 *
	 * Requires "External Files in Media Library" 6.0.0 or newer.
	 *
	 * @param array<string,mixed> $fields     List of fields.
	 * @param string              $connection The name of the connection.
	 *
	 * @return bool
	 */
	public function is_fields_matching_connection( array $fields, string $connection ): bool {
		// get the endpoint defined via constant.
		$constant_endpoint = $this->get_constant_value( 'endpoint', $connection );

		// use the notation of the constant, if both endpoints are the same.
		$endpoint = $this->get_field_value( $fields, 'endpoint' );
		if ( ! is_null( $constant_endpoint ) && '' !== $endpoint && strtolower( $this->normalize_url( $endpoint ) ) === strtolower( $this->normalize_url( $constant_endpoint ) ) ) {
			$fields['endpoint']['value'] = $constant_endpoint;
		}

		// return the result of the check.
		return parent::is_fields_matching_connection( $fields, $connection );
	}

	/**
	 * Return the region for the given fields.
	 *
	 * @param array<string,mixed> $fields List of fields.
	 *
	 * @return string
	 */
	public function get_region( array $fields ): string {
		// get the value.
		$region = $this->get_field_value( $fields, 'region' );

		// use the default region, if no region is set.
		if ( '' === $region ) {
			return $this->get_default_region();
		}

		// return the region.
		return $region;
	}

	/**
	 * Return whether path-style URLs should be used for the given fields.
	 *
	 * @param array<string,mixed> $fields List of fields.
	 *
	 * @return bool
	 */
	public function is_path_style( array $fields ): bool {
		// presets with fixed regions use virtual-hosted URLs.
		if ( $this->has_fixed_regions() ) {
			return false;
		}

		// use the default, if the field is not set.
		if ( ! isset( $fields['path_style'] ) || ! is_array( $fields['path_style'] ) || ! array_key_exists( 'value', $fields['path_style'] ) || '' === $fields['path_style']['value'] || is_null( $fields['path_style']['value'] ) ) {
			return true;
		}

		// return the value.
		return $this->is_enabled_value( $fields['path_style']['value'] );
	}

	/**
	 * Return whether checksums should only be sent if they are required for the given fields.
	 *
	 * @param array<string,mixed> $fields List of fields.
	 *
	 * @return bool
	 */
	public function is_checksum_when_required( array $fields ): bool {
		// bail if the field is not set.
		if ( ! isset( $fields['checksum_when_required']['value'] ) ) {
			return false;
		}

		// return the value.
		return $this->is_enabled_value( $fields['checksum_when_required']['value'] );
	}

	/**
	 * Return the public base URL for the files of the given fields, without trailing slash.
	 *
	 * This is the configured public URL or, if not set, the URL of the bucket.
	 *
	 * @param array<string,mixed> $fields List of fields.
	 *
	 * @return string
	 */
	public function get_public_base_url( array $fields ): string {
		// get the configured public URL.
		$public_url = $this->normalize_url( $this->get_field_value( $fields, 'public_url' ) );

		// bail if it is set.
		if ( '' !== $public_url ) {
			return $public_url;
		}

		// return the URL of the bucket.
		return rtrim( $this->get_bucket_url( $fields ), '/' );
	}

	/**
	 * Return the value of a field as string.
	 *
	 * @param array<string,mixed> $fields     List of fields.
	 * @param string              $field_name The name of the field.
	 *
	 * @return string
	 */
	protected function get_field_value( array $fields, string $field_name ): string {
		// bail if field is not set.
		if ( ! isset( $fields[ $field_name ] ) || ! is_array( $fields[ $field_name ] ) || ! isset( $fields[ $field_name ]['value'] ) || ! is_scalar( $fields[ $field_name ]['value'] ) ) {
			return '';
		}

		// return the value.
		return trim( (string) $fields[ $field_name ]['value'] );
	}

	/**
	 * Return whether the given value of a checkbox is enabled.
	 *
	 * @param mixed $value The value.
	 *
	 * @return bool
	 */
	private function is_enabled_value( mixed $value ): bool {
		// bail if value is a bool.
		if ( is_bool( $value ) ) {
			return $value;
		}

		// return the value as bool (e.g. "1", "true", "on" or "yes").
		return is_scalar( $value ) && filter_var( $value, FILTER_VALIDATE_BOOLEAN );
	}

	/**
	 * Return the normalized URL: with scheme (HTTPS, if missing) and without trailing slash.
	 *
	 * @param string $url The URL.
	 *
	 * @return string
	 */
	public function normalize_url( string $url ): string {
		// remove spaces.
		$url = trim( $url );

		// bail if URL is empty.
		if ( '' === $url ) {
			return '';
		}

		// add the scheme, if it is missing.
		if ( ! preg_match( '/^https?:\/\//i', $url ) ) {
			$url = 'https://' . $url;
		}

		// return the URL without trailing slash.
		return rtrim( $url, '/' );
	}

	/**
	 * Return list of fields we need for this listing.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function get_fields(): array {
		// set fields, if they are empty atm.
		if ( empty( $this->fields ) ) {
			// get the prepared values for the fields.
			$values = $this->get_field_values();

			// whether the values are from the settings or the profile.
			$is_configured = ! $this->is_mode( 'manually' );

			// create the fields.
			$fields = array();
			foreach ( $this->get_field_definitions() as $field_name => $definition ) {
				// get the value.
				$value = isset( $values[ $field_name ] ) ? $values[ $field_name ] : $definition['default'];

				// create the field.
				$field = array(
					'name'        => $field_name,
					'type'        => $definition['type'],
					'label'       => $definition['label'],
					'placeholder' => $definition['placeholder'],
					'value'       => $value,
				);

				// add the description.
				if ( '' !== $definition['description'] ) {
					$field['description'] = $definition['description'];
				}

				// mark credentials.
				if ( $definition['credential'] ) {
					$field['credential'] = true;
				}

				// mark optional fields.
				if ( ! $definition['required'] ) {
					$field['not_required'] = true;
				}

				// add the options for the regions.
				if ( 'select' === $definition['type'] ) {
					$field['options'] = $this->get_regions_for_react();
				}

				// set the values from the settings or the profile as not editable.
				$field['readonly'] = $is_configured && ( 'checkbox' === $definition['type'] || ! empty( $value ) );

				// add it to the list.
				$fields[ $field_name ] = $field;
			}

			// set the fields.
			$this->fields = $fields; // @phpstan-ignore property.notFound
		}

		// return the list of fields.
		return parent::get_fields();
	}

	/**
	 * Return the form title.
	 *
	 * @return string
	 */
	public function get_form_title(): string {
		// bail if credentials are set.
		if ( $this->has_credentials() ) {
			/* translators: %1$s will be replaced by the name of the platform. */
			return sprintf( __( 'Connect to your %1$s bucket', 'external-files-from-aws-s3' ), $this->get_label() );
		}

		// return default title.
		return __( 'Enter your credentials', 'external-files-from-aws-s3' );
	}

	/**
	 * Return the form description.
	 *
	 * @return string
	 */
	public function get_form_description(): string {
		// get the fields.
		$has_credentials_set = $this->has_credentials();

		// if access token is set in plugin settings.
		if ( $this->is_mode( 'global' ) ) {
			if ( $has_credentials_set && ! current_user_can( 'manage_options' ) ) {
				return __( 'An authentication data has already been set by an administrator in the plugin settings. Just connect for show the files.', 'external-files-from-aws-s3' );
			}

			if ( ! $has_credentials_set && ! current_user_can( 'manage_options' ) ) {
				return __( 'An authentication data must be set by an administrator in the plugin settings.', 'external-files-from-aws-s3' );
			}

			if ( ! $has_credentials_set ) {
				/* translators: %1$s will be replaced by a URL. */
				return sprintf( __( 'Set your authentication data <a href="%1$s">here</a>.', 'external-files-from-aws-s3' ), $this->get_config_url() );
			}

			/* translators: %1$s will be replaced by a URL. */
			return sprintf( __( 'Your authentication data are already set <a href="%1$s">here</a>. Just connect for show the files.', 'external-files-from-aws-s3' ), $this->get_config_url() );
		}

		// if authentication JSON is set per user.
		if ( $this->is_mode( 'user' ) ) {
			if ( ! $has_credentials_set ) {
				/* translators: %1$s will be replaced by a URL. */
				return sprintf( __( 'Set your authentication data <a href="%1$s">in your profile</a>.', 'external-files-from-aws-s3' ), $this->get_config_url() );
			}

			/* translators: %1$s will be replaced by a URL. */
			return sprintf( __( 'Your authentication data are already set <a href="%1$s">in your profile</a>. Just connect for show the files.', 'external-files-from-aws-s3' ), $this->get_config_url() );
		}

		/* translators: %1$s will be replaced by the name of the platform, %2$s by a URL. */
		return sprintf( __( 'Enter your %1$s credentials in this form. How to get them is described <a href="%2$s" target="_blank">here (opens in a new window)</a>.', 'external-files-from-aws-s3' ), $this->get_label(), esc_url( $this->get_documentation_url() ) );
	}

	/**
	 * Return the values depending on the actual mode.
	 *
	 * @return array<string,mixed>
	 */
	private function get_field_values(): array {
		// get the definitions.
		$definitions = $this->get_field_definitions();

		// prepare the return array with the default values.
		$values = array();
		foreach ( $definitions as $field_name => $definition ) {
			$values[ $field_name ] = $definition['default'];
		}

		// get it global, if this is enabled.
		if ( $this->is_mode( 'global' ) ) {
			foreach ( $definitions as $field_name => $definition ) {
				// get the value.
				$value = get_option( $this->get_option_name( $field_name ), 'checkbox' === $definition['type'] ? ( $definition['default'] ? 1 : 0 ) : $definition['default'] );

				// decrypt credentials.
				if ( $definition['credential'] ) {
					$value = Crypt::get_instance()->decrypt( (string) $value );
				}

				// convert checkboxes.
				if ( 'checkbox' === $definition['type'] ) {
					$value = 1 === absint( $value );
				}

				// set the value.
				$values[ $field_name ] = $value;
			}
		}

		// get it user-specific, if this is enabled.
		if ( $this->is_mode( 'user' ) ) {
			// get the user set on an object.
			$user = $this->get_user();

			// bail if user is not available.
			if ( ! $user instanceof WP_User ) {
				return array();
			}

			// get the values.
			foreach ( $definitions as $field_name => $definition ) {
				// get the value.
				$value = Crypt::get_instance()->decrypt( (string) get_user_meta( $user->ID, 'efml_' . $this->get_user_setting_name( $field_name ), true ) );

				// use the default value, if no value is set.
				if ( '' === $value ) {
					continue;
				}

				// convert checkboxes.
				if ( 'checkbox' === $definition['type'] ) {
					$values[ $field_name ] = 1 === absint( $value );
					continue;
				}

				// set the value.
				$values[ $field_name ] = $value;
			}
		}

		// return the resulting list of values.
		return $values;
	}

	/**
	 * Return the export object for this service.
	 *
	 * @return Export_Base|false
	 */
	public function get_export_object(): Export_Base|false {
		// get the class name with method.
		$class_name = $this->export_class_name . '::get_instance';

		// bail if it is not callable.
		if ( ! is_callable( $class_name ) ) {
			return false;
		}

		// get the object.
		$export_obj = $class_name();

		// bail if it is not an export object.
		if ( ! $export_obj instanceof Export_Base ) {
			return false;
		}

		// return the object.
		return $export_obj;
	}

	/**
	 * Return whether the given URL is a URL of this platform: the URL of the bucket or the public URL.
	 *
	 * @param string $url The URL to check.
	 *
	 * @return bool
	 */
	public function is_platform_url( string $url ): bool {
		return $this->is_url_of_fields( $url, $this->get_fields() );
	}

	/**
	 * Return whether the given URL is a URL of the bucket or the public URL of the given fields.
	 *
	 * @param string              $url    The URL to check.
	 * @param array<string,mixed> $fields List of fields.
	 *
	 * @return bool
	 */
	public function is_url_of_fields( string $url, array $fields ): bool {
		return '' !== $this->get_url_prefix_of_fields( $url, $fields );
	}

	/**
	 * Return the prefix of the given URL, if it is the URL of the bucket or the public URL of the given fields.
	 *
	 * Returns an empty string, if the URL is not from these fields.
	 *
	 * @param string              $url    The URL to check.
	 * @param array<string,mixed> $fields List of fields.
	 *
	 * @return string
	 */
	public function get_url_prefix_of_fields( string $url, array $fields ): string {
		// check the possible prefixes.
		foreach ( array( $this->get_bucket_url( $fields ), $this->get_public_base_url( $fields ) ) as $prefix ) {
			// bail if prefix is empty.
			if ( '' === $prefix ) {
				continue;
			}

			// add the trailing slash.
			$prefix = trailingslashit( $prefix );

			// return the prefix, if the URL starts with it.
			if ( str_starts_with( strtolower( $url ), strtolower( $prefix ) ) ) {
				return substr( $url, 0, strlen( $prefix ) );
			}
		}

		// return an empty string as this URL is not from the given fields.
		return '';
	}

	/**
	 * Allow to use URLs of this platform without a check of the content type.
	 *
	 * @param bool   $return_value The return value (true to check the file type, so we return here false).
	 * @param string $url The URL to check.
	 *
	 * @return bool
	 */
	public function allow_content_type( bool $return_value, string $url ): bool {
		// bail if this is not a URL of this platform.
		if ( ! $this->is_platform_url( $url ) ) {
			return $return_value;
		}
		return false;
	}

	/**
	 * Set the real mime type for URLs of this platform.
	 *
	 * @param array<string,mixed> $results The results.
	 * @param string              $url The used URL.
	 *
	 * @return array<string,mixed>
	 */
	public function set_real_mime_type( array $results, string $url ): array {
		// bail if this is not our file URL.
		if ( ! $this->is_platform_url( $url ) ) {
			return $results;
		}

		// get the mime type.
		$mime_type = wp_check_filetype( basename( $url ) );

		// bail if no mime type could be detected.
		if ( empty( $mime_type['type'] ) ) {
			return $results;
		}

		// set the mime type.
		$results['mime-type'] = $mime_type['type'];

		// return the resulting file data.
		return $results;
	}

	/**
	 * Return the public URL of a file.
	 *
	 * @param string              $key The given file key.
	 * @param array<string,mixed> $fields List of fields.
	 *
	 * @return string
	 */
	public function get_public_url_of_file( string $key, array $fields ): string {
		// percent-encode each path segment of the key separately, keeping "/" as separator.
		$encoded_key = implode( '/', array_map( 'rawurlencode', explode( '/', ltrim( $key, '/' ) ) ) );

		// create the URL.
		$url = $this->get_public_base_url( $fields ) . '/' . $encoded_key;

		$instance = $this;
		/**
		 * Filter the public URL of a file in an S3-compatible storage or one of its presets.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 * @param string $url The URL.
		 * @param string $key The key of the file in the bucket.
		 * @param array<string,mixed> $fields List of fields.
		 * @param S3Compatible $instance The platform object.
		 */
		return apply_filters( 'efmlawss3_s3_compatible_public_url', $url, $key, $fields, $instance );
	}

	/**
	 * Return the configuration for the S3 client.
	 *
	 * @param array<string,mixed> $fields List of fields.
	 *
	 * @return array<string,mixed>
	 */
	public function get_client_configuration( array $fields ): array {
		// create the configuration array for the client object.
		$configuration = array(
			'version'                 => 'latest',
			'region'                  => $this->get_region( $fields ),
			'endpoint'                => $this->get_endpoint( $fields ),
			'use_path_style_endpoint' => $this->is_path_style( $fields ),
			'credentials'             => array(
				'key'    => $this->get_field_value( $fields, 'access_key' ),
				'secret' => $this->get_field_value( $fields, 'secret' ),
			),
			'http'                    => array(
				'connect_timeout' => 15,
			),
		);

		// send checksums only if they are required, if this is enabled.
		if ( $this->is_checksum_when_required( $fields ) ) {
			$configuration['request_checksum_calculation'] = 'when_required';
			$configuration['response_checksum_validation'] = 'when_required';
		}

		$instance = $this;
		/**
		 * Filter the configuration of the S3 client for an S3-compatible storage or one of its presets.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 * @param array<string,mixed> $configuration The configuration.
		 * @param array<string,mixed> $fields List of fields.
		 * @param S3Compatible $instance The platform object.
		 */
		return apply_filters( 'efmlawss3_s3_compatible_client_configuration', $configuration, $fields, $instance );
	}

	/**
	 * Return the S3 client object.
	 *
	 * Each failed request is logged with all details via @log_aws_exception().
	 *
	 * @return S3Client
	 */
	public function get_s3_client(): S3Client {
		// get the client.
		$client = $this->get_the_client( $this->get_client_configuration( $this->get_fields() ) );

		// log each failed request and return the client.
		return $this->add_error_log( $client );
	}

	/**
	 * Return whether the connection is configured enough to test it.
	 *
	 * @return bool
	 */
	protected function is_connection_configured(): bool {
		return '' !== $this->get_bucket_url( $this->get_fields() );
	}

	/**
	 * Return the details of the connection, which are added to log entries for failed requests.
	 *
	 * @return array<string,string>
	 */
	public function get_log_context(): array {
		// get the fields.
		$fields = $this->get_fields();

		// return the details.
		return array_merge(
			parent::get_log_context(),
			array(
				__( 'Endpoint', 'external-files-from-aws-s3' ) => $this->get_endpoint( $fields ),
				__( 'Region', 'external-files-from-aws-s3' )   => $this->get_region( $fields ),
				__( 'Path-style URLs', 'external-files-from-aws-s3' ) => $this->is_path_style( $fields ) ? __( 'Yes', 'external-files-from-aws-s3' ) : __( 'No', 'external-files-from-aws-s3' ),
				__( 'Checksums only if required', 'external-files-from-aws-s3' ) => $this->is_checksum_when_required( $fields ) ? __( 'Yes', 'external-files-from-aws-s3' ) : __( 'No', 'external-files-from-aws-s3' ),
				__( 'Public URL', 'external-files-from-aws-s3' ) => $this->get_public_base_url( $fields ),
			)
		);
	}

	/**
	 * Return a cleaned requested URL: the key of the file.
	 *
	 * @param string              $url The requested URL.
	 * @param array<string,mixed> $fields The used fields.
	 *
	 * @return string
	 */
	public function get_requested_url( string $url, array $fields ): string {
		// get the prefix.
		$prefix = $this->get_url_prefix_of_fields( $url, $fields );

		// bail if URL is not from this bucket.
		if ( '' === $prefix ) {
			return $url;
		}

		// return the URL without the prefix.
		return substr( $url, strlen( $prefix ) );
	}

	/**
	 * Return whether given file in the bucket is public available.
	 *
	 * The check is done via HEAD request on the public URL of the file.
	 *
	 * @param string   $file_key The file key.
	 * @param S3Client $s3 The S3 client object.
	 *
	 * @return bool
	 */
	public function is_file_public_available( string $file_key, S3Client $s3 ): bool {
		// create the URL we want to check.
		$url = $this->get_public_url_of_file( $file_key, $this->get_fields() );

		// request the headers only.
		$response = wp_remote_head( $url, array( 'timeout' => 15 ) );

		// get the status.
		$status = wp_remote_retrieve_response_code( $response );

		// return true, if we got HTTP status 200.
		if ( 200 === $status ) {
			return true;
		}

		// log the details, if enabled.
		if ( $this->log_public_check_failures ) {
			/* translators: %1$s will be replaced by a URL. */
			$message = sprintf( __( 'The file is not public available under the URL %1$s.', 'external-files-from-aws-s3' ), '<code>' . esc_html( $url ) . '</code>' );
			if ( $response instanceof WP_Error ) {
				$message .= '<br>' . esc_html__( 'Error message', 'external-files-from-aws-s3' ) . ': <code>' . esc_html( $response->get_error_message() ) . '</code>';
			} else {
				$message .= '<br>' . esc_html__( 'HTTP status', 'external-files-from-aws-s3' ) . ': <code>' . esc_html( (string) $status ) . '</code>';
			}
			$message .= '<br>' . esc_html__( 'The files must be public readable to use them as external files. Check the permissions of the bucket (e.g. its policy or public access) or set the public URL.', 'external-files-from-aws-s3' );
			Log::get_instance()->create( $message, $url, 'error' );
		}

		// return false as the file is not public available.
		return false;
	}

	/**
	 * Return the filtered query for the list of files in the bucket.
	 *
	 * @param array<string,mixed> $query The query.
	 * @param string              $directory The requested directory.
	 *
	 * @return array<string,mixed>
	 */
	protected function filter_query_params( array $query, string $directory ): array {
		// change the query for the requested directory.
		$query = $this->change_file_query( $query );

		$instance = $this;
		/**
		 * Filter the query for files in an S3-compatible storage or one of its presets.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 * @param array<string,mixed> $query The query.
		 * @param string $directory The URL.
		 * @param S3Compatible $instance The platform object.
		 */
		return apply_filters( 'efmlawss3_s3_compatible_query_params', $query, $directory, $instance );
	}

	/**
	 * Return whether the given file from the bucket should be hidden in the directory listing.
	 *
	 * @param array<string,mixed> $file The array with the file data.
	 * @param string              $directory The requested directory.
	 *
	 * @return bool
	 */
	protected function is_file_hidden( array $file, string $directory ): bool {
		// check whether the file type is not allowed.
		$hidden = $this->prevent_not_allowed_files( false, $file, $directory );

		$instance = $this;
		/**
		 * Filter whether given file in an S3-compatible storage or one of its presets should be hidden.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 *
		 * @param bool $hidden True if it should be hidden.
		 * @param array<string,mixed> $file The array with the file data.
		 * @param string $directory The requested directory.
		 * @param S3Compatible $instance The platform object.
		 */
		return (bool) apply_filters( 'efmlawss3_service_s3_compatible_hide_file', $hidden, $file, $directory, $instance );
	}

	/**
	 * Return the regions for the settings.
	 *
	 * @return array<string,string>
	 */
	protected function get_regions_for_settings(): array {
		// create the list.
		$list = array();
		foreach ( $this->get_regions() as $region => $settings ) {
			$list[ $region ] = isset( $settings['label'] ) ? $settings['label'] : $region;
		}

		// return the list.
		return $list;
	}

	/**
	 * Convert the region list to a react-compatible array.
	 *
	 * @return array<int,array<string,string>>
	 */
	protected function get_regions_for_react(): array {
		// create list for react.
		$regions_for_react = array();

		// add each region to the list.
		foreach ( $this->get_regions_for_settings() as $key => $value ) {
			$regions_for_react[] = array(
				'value' => $key,
				'label' => $value,
			);
		}

		// return the resulting list.
		return $regions_for_react;
	}

	/**
	 * Return further search terms of this service, separated by commas (e.g. for the command palette).
	 *
	 * Requires "External Files in Media Library" 6.0.0 or newer.
	 *
	 * @return string
	 */
	protected function get_keywords(): string {
		return 'S3, MinIO, Linode, Akamai, Vultr, endpoint, object storage, bucket, cloud';
	}
}
