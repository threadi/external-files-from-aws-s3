<?php
/**
 * File to handle the Backplaze S3 support as directory listing.
 *
 * @package external-files-from-aws-s3
 */

namespace ExternalFilesFromAwsS3\Platforms;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use easyDirectoryListingForWordPress\Crypt;
use ExternalFilesFromAwsS3\Platform_Base;
use ExternalFilesFromAwsS3\Platforms\BackplazeS3\Export;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Fields\Number;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Fields\Password;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Fields\Select;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Fields\Text;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Fields\TextInfo;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Page;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Section;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Settings;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Tab;
use ExternalFilesInMediaLibrary\ExternalFiles\Export_Base;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Languages;
use ExternalFilesInMediaLibrary\Plugin\Log;
use ExternalFilesInMediaLibrary\Services\Service;
use WP_Error;
use WP_User;

/**
 * Object to handle support for S3-based directory listing.
 */
class BackplazeS3 extends Platform_Base implements Service {
	/**
	 * The object name.
	 *
	 * @var string
	 */
	protected string $name = 'backplaze-s3';

	/**
	 * The public label.
	 *
	 * @var string
	 */
	protected string $label = 'Backplaze S3';

	/**
	 * The class name of the protocol class.
	 *
	 * @var string
	 */
	protected string $protocol_class_name = 'ExternalFilesFromAwsS3\Platforms\BackplazeS3\Protocol';

	/**
	 * Name of the configuration object.
	 *
	 * @var string
	 */
	protected string $configuration_object_name = 'ExternalFilesFromAwsS3\Platforms\BackplazeS3\Configuration';

	/**
	 * Slug of settings tab.
	 *
	 * @var string
	 */
	protected string $settings_sub_tab = 'eml_backplaze_s3';

	/**
	 * Instance of actual object.
	 *
	 * @var ?BackplazeS3
	 */
	private static ?BackplazeS3 $instance = null;

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
	 * @return BackplazeS3
	 */
	public static function get_instance(): BackplazeS3 {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Run during activation of the plugin.
	 *
	 * @return void
	 */
	public function activation(): void {}

	/**
	 * Initialize this object.
	 *
	 * @return void
	 */
	public function init(): void {
		// use parent initialization.
		parent::init();

		// add settings.
		add_action( 'init', array( $this, 'init_backplaze_s3' ), 30 );

		// bail if user has no capability for this service.
		if ( ! current_user_can( 'efml_cap_' . $this->get_name() ) ) {
			return;
		}

		// set title.
		$this->title = __( 'Choose file(s) from your Backplaze S3 bucket', 'external-files-from-aws-s3' ); // @phpstan-ignore property.notFound

		// use our own hooks.
		add_filter( 'efmlawss3_service_backplaze_s3_hide_file', array( $this, 'prevent_not_allowed_files' ), 10, 3 );
		add_filter( 'efmlawss3_backplaze_s3_query_params', array( $this, 'change_file_query' ) );
		add_filter( 'efml_http_check_content_type', array( $this, 'allow_content_type' ), 10, 2 );
		add_filter( 'efml_files_check_content_type', array( $this, 'allow_content_type' ), 10, 2 );
		add_filter( 'efml_external_file_infos', array( $this, 'set_real_mime_type' ), 10, 2 );
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
		if ( empty( $this->get_fields() ) ) {
			// create error object.
			$error = new WP_Error();
			$error->add( 'efml_service_backplaze_s3', __( 'No credentials set for this Backplaze S3 connection!', 'external-files-from-aws-s3' ) );

			// add it to the list.
			$this->add_error( $error );

			// return false to login check.
			return false;
		}

		// get S3 client to check if credentials are ok.
		$s3 = $this->get_s3_client();
		try {
			// try to load the requested bucket.
			$s3->listObjectsV2( array( 'Bucket' => $this->get_bucket_name() ) );

			// return true if it could be loaded.
			return true;
		} catch ( S3Exception $e ) {
			// create error object.
			$error = new WP_Error();
			/* translators: %1$d will be replaced by an HTTP-status code like 403. */
			$error->add( 'efml_service_backplaze_s3', sprintf( __( 'Credentials and/or bucket are not valid. Backplaze S3 returns with HTTP-Status %1$d!', 'external-files-from-aws-s3' ), $e->getStatusCode() ) );

			// add it to the list.
			$this->add_error( $error );

			// add log entry.
			/* translators: %1$d will be replaced by an HTTP-status (like 301). */
			Log::get_instance()->create( sprintf( __( 'Credentials and/or bucket are not valid. Backplaze S3 returns with HTTP-Status %1$d! Error:', 'external-files-from-aws-s3' ), $e->getStatusCode() ) . ' <code>' . $e->getMessage() . '</code>', '', 'error' );

			// return false to prevent any further actions.
			return false;
		}
	}

	/**
	 * Enable WP CLI for Backplaze S3 tasks.
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
		// bail if no bucket is set.
		if ( empty( $this->get_bucket_name() ) ) {
			return '/';
		}

		// return the URL with bucket.
		return $this->get_url_mark( $this->get_bucket_name() );
	}

	/**
	 * Add settings.
	 *
	 * @return void
	 */
	public function init_backplaze_s3(): void {
		// bail if user has no capability for this service.
		if ( ! Helper::is_cli() && ! current_user_can( 'efml_cap_' . $this->get_name() ) ) {
			return;
		}

		// get the settings object.
		$settings_obj = Settings::get_instance();

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

		// add a new tab for settings.
		$tab = $services_tab->get_tab( $this->get_settings_subtab_slug() );

		// bail if tab does not exist.
		if ( ! $tab instanceof Tab ) {
			return;
		}

		// add a section for file statistics.
		$section = $tab->get_section( 'section_' . $this->get_name() . '_main' );

		// bail if tab does not exist.
		if ( ! $section instanceof Section ) {
			return;
		}

		// add settings.
		if ( defined( 'EFML_ACTIVATION_RUNNING' ) || 'global' === get_option( 'eml_' . $this->get_name() . '_credentials_vault' ) ) {
			// add setting.
			$setting = $settings_obj->add_setting( 'eml_backplaze_s3_access_key' );
			$setting->set_section( $section );
			$setting->set_autoload( false );
			$setting->set_type( 'string' );
			$setting->set_default( '' );
			$setting->set_read_callback( array( $this, 'decrypt_value' ) );
			$setting->set_save_callback( array( $this, 'encrypt_value' ) );
			$field = new Text();
			$field->set_title( __( 'Access key', 'external-files-from-aws-s3' ) );
			/* translators: %1$s will be replaced by a URL. */
			$field->set_description( sprintf( __( 'Get your access key as described <a href="%1$s" target="_blank">here (opens in a new window)</a>.', 'external-files-from-aws-s3' ), 'https://docs.aws.amazon.com/solutions/latest/data-transfer-hub/set-up-credentials-for-amazon-s3.html' ) );
			$field->set_placeholder( __( 'The access key', 'external-files-from-aws-s3' ) );
			$setting->set_field( $field );

			// add setting.
			$setting = $settings_obj->add_setting( 'eml_backplaze_s3_secret_key' );
			$setting->set_section( $section );
			$setting->set_autoload( false );
			$setting->set_type( 'string' );
			$setting->set_default( '' );
			$setting->set_read_callback( array( $this, 'decrypt_value' ) );
			$setting->set_save_callback( array( $this, 'encrypt_value' ) );
			$field = new Password();
			$field->set_title( __( 'Secret key', 'external-files-from-aws-s3' ) );
			$field->set_placeholder( __( 'The secret key', 'external-files-from-aws-s3' ) );
			$setting->set_field( $field );

			// add setting.
			$setting = $settings_obj->add_setting( 'eml_backplaze_s3_bucket' );
			$setting->set_section( $section );
			$setting->set_autoload( false );
			$setting->set_type( 'string' );
			$setting->set_default( '' );
			$field = new Text();
			$field->set_title( __( 'Bucket', 'external-files-from-aws-s3' ) );
			$field->set_placeholder( __( 'The bucket you want to use.', 'external-files-from-aws-s3' ) );
			$setting->set_field( $field );
		}

		// show hint for user settings.
		if ( 'user' === get_option( 'eml_' . $this->get_name() . '_credentials_vault' ) ) {
			$setting = $settings_obj->add_setting( 'eml_backplaze_s3_credential_location_hint' );
			$setting->set_section( $section );
			$setting->set_show_in_rest( false );
			$setting->prevent_export( true );
			$field = new TextInfo();
			$field->set_title( __( 'Hint', 'external-files-from-aws-s3' ) );
			/* translators: %1$s will be replaced by a URL. */
			$field->set_description( sprintf( __( 'Each user will find its settings in his own <a href="%1$s">user profile</a>.', 'external-files-from-aws-s3' ), $this->get_config_url() ) );
			$setting->set_field( $field );
		}

		// add setting.
		$setting = $settings_obj->add_setting( 'eml_backplaze_s3_region' );
		$setting->set_section( $section );
		$setting->set_type( 'string' );
		$setting->set_default( $this->get_mapping_region() );
		$field = new Select();
		$field->set_title( __( 'Choose the region', 'external-files-from-aws-s3' ) );
		$field->set_options( $this->get_regions() );
		$setting->set_field( $field );

		// add setting to show also trashed files.
		$setting = $settings_obj->add_setting( 'eml_backplaze_s3_import_limit' );
		$setting->set_section( $section );
		$setting->set_type( 'integer' );
		$setting->set_default( 10 );
		$field = new Number();
		$field->set_title( __( 'Max. files to load during import per iteration', 'external-files-from-aws-s3' ) );
		$field->set_description( __( 'This value specifies how many files should be loaded during a directory import. The higher the value, the greater the likelihood of timeouts during import.', 'external-files-from-aws-s3' ) );
		$field->set_setting( $setting );
		$field->set_readonly( $this->is_disabled() ); // @phpstan-ignore method.notFound
		$setting->set_field( $field );
	}

	/**
	 * Return list of user settings.
	 *
	 * @return array<string,mixed>
	 */
	public function get_user_settings(): array {
		$list = array(
			'backplaze_s3_access_key' => array(
				'label'       => __( 'Access key', 'external-files-from-aws-s3' ),
				'field'       => 'text',
				'placeholder' => __( 'The access key', 'external-files-from-aws-s3' ),
			),
			'backplaze_s3_secret_key' => array(
				'label'       => __( 'Secret key', 'external-files-from-aws-s3' ),
				'field'       => 'password',
				'placeholder' => __( 'The secret key', 'external-files-from-aws-s3' ),
			),
			'backplaze_s3_bucket'     => array(
				'label'       => __( 'Bucket', 'external-files-from-aws-s3' ),
				'field'       => 'text',
				'placeholder' => __( 'The bucket you want to use', 'external-files-from-aws-s3' ),
			),
		);

		/**
		 * Filter the list of possible user settings for Backplaze S3.
		 *
		 * @since 1.0.0 Available since 1.0.0.
		 * @param array<string,mixed> $list The list of settings.
		 */
		return apply_filters( 'efmlawss3_service_backplaze_s3_user_settings', $list );
	}

	/**
	 * Return the URL mark, which identifies Backplaze S3 URLs within this plugin.
	 *
	 * @return string
	 */
	public function get_url_mark(): string {
		// get the fields.
		$fields = $this->get_fields();

		// return the URL.
		return 'https://' . $fields['bucket']['value'] . '.s3.' . $fields['region']['value'] . '.backblazeb2.com/';
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

			// set the fields.
			$this->fields = array( // @phpstan-ignore property.notFound
				'access_key' => array(
					'name'        => 'access_key',
					'type'        => 'text',
					'label'       => __( 'Access key', 'external-files-from-aws-s3' ),
					'placeholder' => __( 'The access key', 'external-files-from-aws-s3' ),
					'credential'  => true,
					'value'       => $values['access_key'],
					'readonly'    => ! empty( $values['access_key'] ),
				),
				'secret'     => array(
					'name'        => 'secret',
					'type'        => 'password',
					'label'       => __( 'Secret key', 'external-files-from-aws-s3' ),
					'placeholder' => __( 'The secret key', 'external-files-from-aws-s3' ),
					'credential'  => true,
					'value'       => $values['secret_key'],
					'readonly'    => ! empty( $values['secret_key'] ),
				),
				'bucket'     => array(
					'name'        => 'bucket',
					'type'        => 'text',
					'label'       => __( 'Bucket', 'external-files-from-aws-s3' ),
					'placeholder' => __( 'The bucket you want to use', 'external-files-from-aws-s3' ),
					'value'       => $values['bucket'],
					'readonly'    => ! empty( $values['bucket'] ),
				),
				'region'     => array(
					'name'        => 'region',
					'type'        => 'select',
					'label'       => __( 'Region', 'external-files-from-aws-s3' ),
					'placeholder' => __( 'The region the bucket is located in', 'external-files-from-aws-s3' ),
					'options'     => $this->get_regions_for_react(),
					'value'       => $values['region'],
					'readonly'    => 'manually' === $this->get_mode() && ! empty( $values['region'] ),
				),
			);
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
			return __( 'Connect to your Backplaze S3 bucket', 'external-files-from-aws-s3' );
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

		/* translators: %1$s will be replaced by a URL. */
		return sprintf( __( 'Enter your Backplace S3 credentials in this form. How to get them for Backplaze S3 is described <a href="%1$s">here</a>.', 'external-files-from-aws-s3' ), 'https://developers.cloudflare.com/r2/api/tokens/' );
	}

	/**
	 * Return the values depending on the actual mode.
	 *
	 * @return array<string,mixed>
	 */
	private function get_field_values(): array {
		// prepare the return array.
		$values = array(
			'access_key' => '',
			'secret_key' => '',
			'bucket'     => '',
			'region'     => get_option( 'eml_backplaze_s3_region', '' ),
		);

		// get it global, if this is enabled.
		if ( $this->is_mode( 'global' ) ) {
			$values['access_key'] = Crypt::get_instance()->decrypt( get_option( 'eml_backplaze_s3_access_key', '' ) );
			$values['secret_key'] = Crypt::get_instance()->decrypt( get_option( 'eml_backplaze_s3_secret_key', '' ) );
			$values['bucket']     = get_option( 'eml_backplaze_s3_bucket', '' );
			$values['region']     = get_option( 'eml_backplaze_s3_region', '' );
		}

		// save it user-specific, if this is enabled.
		if ( $this->is_mode( 'user' ) ) {
			// get the user set on an object.
			$user = $this->get_user();

			// bail if user is not available.
			if ( ! $user instanceof WP_User ) {
				return array();
			}

			// get the values.
			$values['access_key'] = Crypt::get_instance()->decrypt( get_user_meta( $user->ID, 'efml_backplaze_s3_access_key', true ) );
			$values['secret_key'] = Crypt::get_instance()->decrypt( get_user_meta( $user->ID, 'efml_backplaze_s3_secret_key', true ) );
			$values['bucket']     = Crypt::get_instance()->decrypt( get_user_meta( $user->ID, 'efml_backplaze_s3_bucket', true ) );
			$values['region']     = Crypt::get_instance()->decrypt( get_user_meta( $user->ID, 'efml_backplaze_s3_region', true ) );
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
		return Export::get_instance();
	}

	/**
	 * Allow to use Backplaze S3 URLs.
	 *
	 * @param bool   $return_value The return value (true to check the file type, so we return here false).
	 * @param string $url The URL to check.
	 *
	 * @return bool
	 */
	public function allow_content_type( bool $return_value, string $url ): bool {
		// bail if this is not a Backplaze S3 URL.
		if ( ! str_contains( $url, 'backplazeb2.com' ) ) {
			return $return_value;
		}
		return false;
	}

	/**
	 * Set the real mime type for Backplaze S3 URLs.
	 *
	 * @param array<string,mixed> $results The results.
	 * @param string              $url The used URL.
	 *
	 * @return array<string,mixed>
	 */
	public function set_real_mime_type( array $results, string $url ): array {
		// bail if this is not our file URL.
		if ( ! str_contains( $url, 'backplazeb2.com' ) ) {
			return $results;
		}

		// get the mime type.
		$mime_type = wp_check_filetype( basename( $url ) );

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
		return sprintf(
			'https://%s.s3.%s.backblazeb2.com/%s',
			$fields['bucket']['value'],
			$fields['region']['value'],
			$key
		);
	}

	/**
	 * Return the S3 client object.
	 *
	 * @return S3Client
	 */
	public function get_s3_client(): S3Client {
		// get the fields.
		$fields = $this->get_fields();

		// get the URL for the endpoint.
		$endpoint_url = 'https://s3.' . $fields['region']['value'] . '.backblazeb2.com';

		// create the configuration array for the client object.
		$configuration = array(
			'version'     => 'latest',
			'region'      => $fields['region']['value'],
			'endpoint'    => $endpoint_url,
			'credentials' => array(
				'key'    => $fields['access_key']['value'],
				'secret' => $fields['secret']['value'],
			),
		);
		return $this->get_the_client( $configuration );
	}

	/**
	 * Return a cleaned requested URL.
	 *
	 * @param string              $url The requested URL.
	 * @param array<string,mixed> $fields The used fields.
	 *
	 * @return string
	 */
	public function get_requested_url( string $url, array $fields ): string {
		return str_replace( $this->get_url_mark(), '', $url );
	}

	/**
	 * Return whether given file in the bucket is public available.
	 *
	 * @param string   $file_key The file key.
	 * @param S3Client $s3 The S3 client object.
	 *
	 * @return bool
	 */
	public function is_file_public_available( string $file_key, S3Client $s3 ): bool {
		// get the fields.
		$fields = $this->get_fields();

		// create the URL we want to check.
		$url = 'https://' . $fields['bucket']['value'] . '.s3.' . $fields['region']['value'] . '.backblazeb2.com/' . $file_key;

		// request the headers only.
		$headers_response = wp_remote_head( $url );

		// return true, if we got HTTP status 200.
		return 200 === wp_remote_retrieve_response_code( $headers_response );
	}

	/**
	 * Return list of Backplaze S3 regions for settings.
	 *
	 * @return array<string,string>
	 */
	private function get_regions(): array {
		// create the list.
		$regions = array(
			'us-west-004'      => 'us-west-004',
			'us-east-005'      => 'us-east-005',
			'eu-central-003'   => 'eu-central-003',
			'ap-southeast-002' => 'ap-southeast-002',
		);

		/**
		 * Filter the possible regions for Backplaze S3.
		 *
		 * @since 1.0.0 Available since 1.0.0.
		 * @param array<string,string> $regions List of regions.
		 */
		return apply_filters( 'efmlawss3_service_backplaze_s3_regions', $regions );
	}

	/**
	 * Convert the region list to a react-compatible array.
	 *
	 * @return array<int,array<string,string>>
	 */
	private function get_regions_for_react(): array {
		// get the regions.
		$regions = $this->get_regions();

		// create list for react.
		$regions_for_react = array();

		// add each region to the list.
		foreach ( $regions as $key => $value ) {
			$regions_for_react[] = array(
				'value' => $key,
				'label' => $value,
			);
		}

		// return the resulting list.
		return $regions_for_react;
	}

	/**
	 * Try to map the region according to the used WordPress language.
	 *
	 * @return string
	 */
	private function get_mapping_region(): string {
		// get actual language.
		$language = Languages::get_instance()->get_current_lang();

		// set nothing as default.
		$default_region = '';

		// use eu-central-003 for german.
		if ( Languages::get_instance()->is_german_language() ) {
			return 'eu-central-003';
		}

		/**
		 * Filter the default Backplaze S3 region.
		 *
		 * @since 1.0.0 Available since 1.0.0.
		 * @param string $default_region The default region.
		 * @param string $language The actual language.
		 */
		return apply_filters( 'efmlawss3_service_backplaze_s3_default_region', $default_region, $language );
	}
}
