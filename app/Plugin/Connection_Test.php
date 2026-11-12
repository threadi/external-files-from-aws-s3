<?php
/**
 * File to handle the complete connection test for S3-compatible storages.
 *
 * @package external-files-from-aws-s3
 */

namespace ExternalFilesFromAwsS3\Plugin;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesFromAwsS3\Platform_Base;
use ExternalFilesInMediaLibrary\ExternalFiles\Taxonomy;
use ExternalFilesInMediaLibrary\Plugin\Admin\Directory_Listing;
use ExternalFilesInMediaLibrary\Plugin\Settings;
use ExternalFilesInMediaLibrary\Services\Services;

/**
 * Object to handle the complete connection test for S3-compatible storages.
 */
class Connection_Test {
	/**
	 * The name of the AJAX action and the nonce.
	 *
	 * @var string
	 */
	private const ACTION = 'efmlawss3_connection_test';

	/**
	 * Instance of actual object.
	 *
	 * @var ?Connection_Test
	 */
	private static ?Connection_Test $instance = null;

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
	 * @return Connection_Test
	 */
	public static function get_instance(): Connection_Test {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize this object.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'wp_ajax_' . self::ACTION, array( $this, 'run_via_ajax' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'add_script' ) );
	}

	/**
	 * Add the script for the connection test in the backend.
	 *
	 * @param string $hook The used hook.
	 *
	 * @return void
	 */
	public function add_script( string $hook ): void {
		// bail if page is used where we do not use it.
		if ( ! in_array( $hook, array( 'media_page_efml_local_directories', 'upload.php', 'settings_page_eml_settings', 'options-general.php', 'edit-tags.php', 'term.php' ), true ) ) {
			return;
		}

		// add the script.
		wp_enqueue_script(
			'efmlawss3-connection-test',
			Helper::get_plugin_url() . 'admin/connection-test.js',
			array( 'jquery' ),
			Helper::get_file_version( Helper::get_plugin_dir() . 'admin/connection-test.js' ),
			true
		);

		// add the variables for the script.
		wp_localize_script(
			'efmlawss3-connection-test',
			'efmlawss3ConnectionTestVars',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( self::ACTION ),
				'title'    => __( 'Connection test is running', 'external-files-from-aws-s3' ),
				'text'     => __( 'Please wait a moment.', 'external-files-from-aws-s3' ),
			)
		);
	}

	/**
	 * Return the platform of this plugin with the given name.
	 *
	 * @param string $name The name of the platform.
	 *
	 * @return Platform_Base|false
	 */
	public function get_platform_by_name( string $name ): Platform_Base|false {
		// check each service.
		foreach ( Services::get_instance()->get_services_as_objects() as $service_obj ) {
			// bail if this is not a platform of this plugin.
			if ( ! $service_obj instanceof Platform_Base ) {
				continue;
			}

			// bail if this is not the searched one.
			if ( $service_obj->get_name() !== $name ) {
				continue;
			}

			// return the actual instance of the platform.
			return $service_obj::get_instance();
		}

		// return false as the platform could not be found.
		return false;
	}

	/**
	 * Run the connection test via AJAX request and return a dialog with the results.
	 *
	 * @return void
	 */
	public function run_via_ajax(): void {
		// check nonce.
		check_ajax_referer( self::ACTION, 'nonce' );

		// create initial response dialog.
		$dialog = array(
			'detail' => array(
				'className' => 'efml',
				'title'     => __( 'Connection test', 'external-files-from-aws-s3' ),
				'texts'     => array(),
				'buttons'   => array(
					array(
						'action'  => 'closeDialog();',
						'variant' => 'primary',
						'text'    => __( 'OK', 'external-files-from-aws-s3' ),
					),
				),
			),
		);

		// get the platform.
		$type     = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : '';
		$platform = $this->get_platform_by_name( $type );

		// bail if platform is unknown or the user is not allowed to use it.
		if ( ! $platform instanceof Platform_Base || ! current_user_can( 'efml_cap_' . $platform->get_name() ) ) {
			$dialog['detail']['texts'][] = '<p>' . __( 'The connection test could not be run.', 'external-files-from-aws-s3' ) . '</p>';
			wp_send_json( $dialog );
		}

		// get the fields from the request.
		$fields = isset( $_POST['fields'] ) ? map_deep( wp_unslash( $_POST['fields'] ), 'sanitize_text_field' ) : array();

		// get the fields from the used external source.
		$term_id = isset( $_POST['term_id'] ) ? absint( $_POST['term_id'] ) : 0;
		if ( $term_id > 0 ) {
			// bail if the actual user is not allowed to use this external source.
			if ( ! Directory_Listing::get_instance()->is_source_allowed_for_current_user( $term_id ) ) {
				$dialog['detail']['texts'][] = '<p><strong>' . __( 'Access not allowed!', 'external-files-from-aws-s3' ) . '</strong></p>';
				wp_send_json( $dialog );
			}

			// get the fields of the external source.
			$term_data = Taxonomy::get_instance()->get_entry( $term_id );
			if ( ! empty( $term_data['fields'] ) ) {
				$fields = $term_data['fields'];
			}
		}

		// set the fields (if none are given, the fields from the settings or the profile are used).
		$platform->set_fields( is_array( $fields ) ? $fields : array() );

		// run the test.
		$steps = $platform->run_connection_test();

		// show the results.
		$all_successful = true;
		$list           = '';
		foreach ( $steps as $step ) {
			// get the state.
			if ( $step['skipped'] ) {
				$state = '<span class="dashicons dashicons-minus"></span> ' . esc_html__( 'Skipped', 'external-files-from-aws-s3' );
			} elseif ( $step['success'] ) {
				$state = '<span class="dashicons dashicons-yes"></span> ' . esc_html__( 'Successful', 'external-files-from-aws-s3' );
			} else {
				$state          = '<span class="dashicons dashicons-no"></span> ' . esc_html__( 'Failed', 'external-files-from-aws-s3' );
				$all_successful = false;
			}

			// add the entry.
			$list .= '<li><strong>' . esc_html( $step['title'] ) . ':</strong> ' . $state . ( '' !== $step['message'] ? '<br>' . esc_html( $step['message'] ) : '' ) . '</li>';
		}

		// set the title and the texts.
		$dialog['detail']['title']   = $all_successful ? __( 'Connection test successful', 'external-files-from-aws-s3' ) : __( 'Connection test failed', 'external-files-from-aws-s3' );
		$dialog['detail']['texts'][] = '<ul>' . $list . '</ul>';
		/* translators: %1$s will be replaced by a URL. */
		$dialog['detail']['texts'][] = '<p>' . sprintf( __( 'All steps are logged with their details in the <a href="%1$s">log</a>. Use them if you contact the support or the provider of your storage.', 'external-files-from-aws-s3' ), esc_url( Settings::get_instance()->get_url( 'eml_logs' ) ) ) . '</p>';

		// return the dialog.
		wp_send_json( $dialog );
	}
}
