<?php
/**
 * File, which handles the updates from GitHub repository for this plugin.
 *
 * @package external-files-from-aws-s3
 */

namespace ExternalFilesFromAwsS3\Plugin;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use stdClass;

/**
 * Object to handle updates from GitHub repository for this plugin.
 */
class Updates {
	/**
	 * The GitHub user.
	 *
	 * @var string
	 */
	private string $github_user = 'threadi';

	/**
	 * The GitHub repository.
	 *
	 * @var string
	 */
	private string $github_repository = 'external-files-from-aws-s3';

	/**
	 * Instance of actual object.
	 *
	 * @var ?Updates
	 */
	private static ?Updates $instance = null;

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
	 * @return Updates
	 */
	public static function get_instance(): Updates {
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
		add_filter( 'update_plugins_github.com', array( $this, 'check' ), 10, 3 );
	}

	/**
	 * Return the update data for this plugin from its latest GitHub release.
	 *
	 * @param array<string,mixed>|false $update      The update data.
	 * @param array<string,mixed>       $plugin_data The plugin headers.
	 * @param string                    $plugin_file The plugin file.
	 *
	 * @return array<string,mixed>|false
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function check( array|false $update, array $plugin_data, string $plugin_file ): array|false {
		// bail if this is not our plugin, as the hook is used for all plugins with a GitHub Update URI.
		if ( plugin_basename( EFMLAWSS3_PLUGIN ) !== $plugin_file || wp_is_development_mode( 'plugin' ) ) {
			return $update;
		}

		// bail if no release is available.
		$release = $this->get_latest_release();
		if ( empty( $release ) ) {
			return $update;
		}

		// WordPress decides on its own whether this is an update (response) or not (no_update).
		return array(
			'slug'         => $this->github_repository,
			'version'      => $release['version'],
			'url'          => 'https://github.com/' . $this->github_user . '/' . $this->github_repository,
			'package'      => $release['package'],
			'tested'       => '7.2',
			'requires_php' => '8.2',
			'icons'        => array( 'default' => Helper::get_plugin_url() . 'gfx/logo.png' ),
		);
	}

	/**
	 * Return version and ZIP of the latest release, cached to respect the rate limit of the GitHub API.
	 *
	 * @return array<string,string>
	 */
	private function get_latest_release(): array {
		$release = get_site_transient( 'efmlawss3_latest_release' );
		if ( is_array( $release ) ) {
			return $release;
		}

		$response = wp_remote_get(
			'https://api.github.com/repos/' . $this->github_user . '/' . $this->github_repository . '/releases/latest',
			array(
				'timeout' => 30,
				'headers' => array( 'Accept' => 'application/vnd.github+json' ),
			)
		);

		// remember errors for one hour, so a failing API is not requested on each page load.
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			set_site_transient( 'efmlawss3_latest_release', array(), HOUR_IN_SECONDS );
			return array();
		}

		$data    = json_decode( wp_remote_retrieve_body( $response ), true );
		$release = array();
		foreach ( ( is_array( $data ) && ! empty( $data['assets'] ) ) ? $data['assets'] : array() as $asset ) {
			if ( 'application/zip' === $asset['content_type'] ) {
				$release = array(
					'version' => (string) preg_replace( '/[^0-9.]/', '', (string) $data['tag_name'] ),
					'package' => (string) $asset['browser_download_url'],
				);
				break;
			}
		}

		set_site_transient( 'efmlawss3_latest_release', $release, 6 * HOUR_IN_SECONDS );
		return $release;
	}
}
