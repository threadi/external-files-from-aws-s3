<?php
/**
 * File to handle the Cloudflare R2 support as directory listing.
 *
 * @package external-files-from-aws-s3
 */

namespace ExternalFilesFromAwsS3;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use ExternalFilesInMediaLibrary\ExternalFiles\ImportDialog;
use ExternalFilesInMediaLibrary\Plugin\Admin\Directory_Listing;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Services\Service_Base;
use WP_Error;
use WP_User;

/**
 * Object for handle tasks for each platform object.
 */
class Platform_Base extends Service_Base {
	/**
	 * The class name of the protocol class.
	 *
	 * @var string
	 */
	protected string $protocol_class_name = '';

	/**
	 * Name of the configuration object.
	 *
	 * @var string
	 */
	protected string $configuration_object_name = '';

	/**
	 * Initialize this object.
	 *
	 * @return void
	 */
	public function init(): void {
		// use parent initialization.
		parent::init();

		// bail if user has no capability for this service.
		if ( ! current_user_can( 'efml_cap_' . $this->get_name() ) ) {
			return;
		}

		// use our own hooks.
		add_filter( 'efml_protocols', array( $this, 'add_protocol' ) );
		add_filter( 'efml_directory_listing', array( $this, 'prepare_tree_building' ), 10, 3 );
		add_filter( 'efml_http_header_args', array( $this, 'remove_authorization_header' ), 10, 2 );

		// misc.
		add_action( 'show_user_profile', array( $this, 'add_user_settings' ) );
	}

	/**
	 * Return the protocol class name.
	 *
	 * @return string
	 */
	private function get_protocol_class_name(): string {
		return $this->protocol_class_name;
	}

	/**
	 * Add our own protocol.
	 *
	 * @param array<string> $protocols List of protocols.
	 *
	 * @return array<string>
	 */
	public function add_protocol( array $protocols ): array {
		// add this protocol before the HTTPS-protocol and return resulting list of protocols.
		array_unshift( $protocols, $this->get_protocol_class_name() );

		// return the resulting list.
		return $protocols;
	}

	/**
	 * Return the S3Client object with given credentials.
	 *
	 * @param array<string,mixed> $configuration The configuration to use.
	 *
	 * @return S3Client
	 */
	public function get_the_client( array $configuration ): S3Client {
		return new S3Client( $configuration );
	}

	/**
	 * Return the forward URL after activating this as plugin.
	 *
	 * @return string
	 */
	public function get_forward_url(): string {
		return Directory_Listing::get_instance()->get_view_directory_url( false );
	}

	/**
	 * Prevent visibility of not allowed mime types.
	 *
	 * @param bool                $result True if it should be hidden.
	 * @param array<string,mixed> $file The array with the file data.
	 * @param string              $url The requested directory.
	 *
	 * @return bool
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function prevent_not_allowed_files( bool $result, array $file, string $url ): bool {
		// bail if setting is disabled.
		if ( 1 !== absint( get_option( 'eml_directory_listing_hide_not_supported_file_types' ) ) ) {
			return $result;
		}

		// get content type for this file.
		$mime_type = wp_check_filetype( basename( $file['Key'] ) );

		// return whether this file type is allowed (false) or not (true).
		return ! in_array( $mime_type['type'], Helper::get_allowed_mime_types(), true );
	}

	/**
	 * Rebuild the resulting list to remove the pagination folders for clean view of the files.
	 *
	 * @param array<string,mixed> $listing The resulting list.
	 * @param string              $url The called URL.
	 * @param string              $service The used service.
	 *
	 * @return array<string,mixed>
	 */
	public function prepare_tree_building( array $listing, string $url, string $service ): array {
		// bail if this is not our service.
		if ( $this->get_name() !== $service ) {
			return $listing;
		}

		// move all directories in the tree.
		foreach ( $listing as $key => $value ) {
			// bail for the main entry.
			if ( $key === $url ) {
				continue;
			}

			// remove the used URL from key to get the directory hierarchy of this entry.
			$stripped_key = str_replace( trailingslashit( $url ), '', $key );

			// get the hierarchy.
			$parts = explode( '/', $stripped_key );

			// clean the URL.
			$cleaned_url = str_replace( $key, '', $url );

			// move the entry to the target entry in tree.
			foreach ( $parts as $part ) {
				// bail on empty part.
				if ( empty( $part ) ) {
					continue;
				}

				// create the index key.
				$index = $cleaned_url . trailingslashit( $part );

				// copy this to the main tree.
				$listing[ $url ]['dirs'][ $index ] = $value;
			}

			// remove the original entry.
			unset( $listing[ $key ] );
		}

		// if the main directory is not requested, search in the tree for the real target.
		if ( $this->directory !== $this->get_directory() ) { // @phpstan-ignore property.notFound
			// search for the tree.
			$searched_tree = $this->get_real_tree( $listing, $this->directory ); // @phpstan-ignore property.notFound

			// return the complete tree if search directory none has been found.
			if ( empty( $searched_tree ) ) {
				return $listing;
			}

			// return the found tree.
			return $searched_tree;
		}

		// return the resulting tree.
		return $listing;
	}

	/**
	 * Return the real tree from given directory.
	 *
	 * @param array<string,mixed> $tree The tree.
	 * @param string              $directory The searched directory path.
	 *
	 * @return array<string,mixed>
	 */
	private function get_real_tree( array $tree, string $directory ): array {
		// create the return value.
		$real_tree = array();

		// loop through the tree.
		foreach ( $tree as $key => $value ) {
			// bail if directory has been found.
			if ( $key === $directory ) {
				return array( $key => $value );
			}

			// check the deeper tree entries.
			$real_tree = $this->get_real_tree( $value['dirs'], $directory );
		}

		// return the found directory.
		return $real_tree;
	}

	/**
	 * Change the query for files.
	 *
	 * @param array<string,mixed> $query The query.
	 *
	 * @return array<string,mixed>
	 */
	public function change_file_query( array $query ): array {
		// bail if directory is not set.
		if ( empty( $this->directory ) ) {
			return $query;
		}

		// bail if no specific directory is set.
		if ( $this->directory === $this->get_directory() ) {
			return $query;
		}

		// get the requested directory.
		$directory = str_replace(
			array(
				$this->get_url_mark( '' ),
				$this->get_url_mark( $this->get_bucket_name() ),
				$this->get_directory(),
				'//',
			),
			array( '', '', '', '/' ),
			$this->directory
		);

		// bail if directory is empty.
		if ( empty( $directory ) ) {
			return $query;
		}

		// add the query for the directory.
		$query['Prefix']    = $directory;
		$query['Delimiter'] = '/';

		// return the resulting query.
		return $query;
	}

	/**
	 * Show option to connect to this platform on the user profile.
	 *
	 * @param WP_User $user The "WP_User" object for the actual user.
	 *
	 * @return void
	 */
	public function add_user_settings( WP_User $user ): void {
		// bail if settings are not user-specific.
		if ( ! $this->is_mode( 'user' ) ) {
			return;
		}

		// bail if customization for this user is not allowed.
		if ( ! ImportDialog::get_instance()->is_customization_allowed() ) {
			return;
		}

		?><h3 id="efml-<?php echo esc_attr( $this->get_name() ); ?>"><?php echo esc_html__( 'AWS S3', 'external-files-from-aws-s3' ); ?></h3>
		<div class="efml-user-settings">
			<?php

			// show settings table.
			$this->get_user_settings_table( absint( $user->ID ) );

			?>
		</div>
		<?php
	}

	/**
	 * Return the name of the configuration object for this platform.
	 *
	 * @return string
	 */
	public function get_configuration_object_name(): string {
		return $this->configuration_object_name;
	}

	/**
	 * Return the S3 client object.
	 *
	 * @return S3Client
	 */
	public function get_s3_client(): S3Client {
		return $this->get_the_client( array() );
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
		return $url;
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
		return false;
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
		return $key;
	}

	/**
	 * Return the bucket from fields.
	 *
	 * @return string
	 */
	public function get_bucket_name(): string {
		// get the fields.
		$fields = $this->get_fields();

		// return nothing if no bucket is set.
		if ( ! isset( $fields['bucket']['value'] ) ) {
			return '';
		}

		// return the bucket from fields.
		return $fields['bucket']['value'];
	}

	/**
	 * Return the directory listing structure.
	 *
	 * @param string $directory The requested directory.
	 *
	 * @return array<int|string,mixed>
	 */
	public function get_directory_listing( string $directory ): array {
		// get the S3Client.
		$s3 = $this->get_s3_client();

		// get list of directories and files in given bucket.
		try {
			// create the query to load the list of files.
			$query = array(
				'Bucket' => $this->get_bucket_name(),
			);

			/**
			 * Filter the query for files in AWS S3.
			 *
			 * @since 1.0.0 Available since 1.0.0.
			 * @param array $query The query.
			 * @param string $directory The URL.
			 */
			$query = apply_filters( 'efmlawss3_aws_s3_query_params', $query, $directory );

			// try to load the requested bucket.
			$result = $s3->listObjectsV2( $query );

			/**
			 * Get list of files.
			 *
			 * This will be all files in the complete bucket incl. subdirectories.
			 */
			$files = $result['Contents'];

			// bail if no data returned.
			if ( ! is_array( $files ) || empty( $files ) ) {
				// create error object.
				$error = new WP_Error();
				$error->add( 'efml_service_s3', __( 'No files returned from AWS S3.', 'external-files-from-aws-s3' ) );

				// add it to the list.
				$this->add_error( $error );

				// do nothing more.
				return array();
			}

			// collect the content of this directory.
			$listing = array(
				'title' => $this->get_directory(),
				'files' => array(),
				'dirs'  => array(),
			);

			// collect list of folders.
			$folders = array();

			// add each file to the list.
			foreach ( $files as $file ) {
				$false = false;
				/**
				 * Filter whether given AWS S3 file should be hidden.
				 *
				 * @since 1.0.0 Available since 1.0.0.
				 *
				 * @param bool $false True if it should be hidden.
				 * @param array<string,mixed> $file The array with the file data.
				 * @param string $directory The requested directory.
				 *
				 * @noinspection PhpConditionAlreadyCheckedInspection
				 */
				if ( apply_filters( 'efmlawss3_service_aws_s3_hide_file', $false, $file, $directory ) ) {
					continue;
				}

				// get directory-data for this file and add the file in the given directories.
				$parts = explode( '/', $file['Key'] );

				// collect the entry.
				$entry = array(
					'title' => basename( $file['Key'] ),
				);

				// if array contains more than 1 entry this file is in a directory.
				if ( end( $parts ) ) {
					// get content-type of this file.
					$mime_type = wp_check_filetype( basename( $file['Key'] ) );

					// bail if file type is not allowed.
					if ( empty( $mime_type['type'] ) ) {
						continue;
					}

					// add settings for entry.
					$entry['s3_key']        = $file['Key'];
					$entry['file']          = $this->get_public_url_of_file( $file['Key'], $this->get_fields() );
					$entry['filesize']      = absint( $file['Size'] );
					$entry['mime-type']     = $mime_type['type'];
					$entry['icon']          = '<span class="dashicons dashicons-media-default" data-type="' . esc_attr( $mime_type['type'] ) . '"></span>';
					$entry['last-modified'] = Helper::get_format_date_time( gmdate( 'Y-m-d H:i:s', absint( $file['LastModified']->format( 'U' ) ) ) );
					$entry['preview']       = '';
				}

				if ( count( $parts ) > 1 ) {
					$the_keys = array_keys( $parts );
					$last_key = end( $the_keys );
					$last_dir = '';
					$dir_path = '';

					// loop through all parent folders, add the directory if it does not exist in the list
					// and add the file to each.
					foreach ( $parts as $key => $dir ) {
						// bail if dir is empty.
						if ( empty( $dir ) ) {
							continue;
						}

						// bail for last entry (is a file).
						if ( $key === $last_key ) {
							// add the file to the last iterated directory.
							$folders[ $last_dir ]['files'][] = $entry;
							continue;
						}

						// add the path.
						$dir_path .= trailingslashit( $dir );

						// create the full path.
						$index = $this->get_directory() . $dir_path;

						// add the directory if it does not exist atm in the main folder list.
						if ( ! isset( $folders[ $index ] ) ) {
							// add the directory to the list.
							$folders[ $index ] = array(
								'title' => $dir,
								'files' => array(),
								'dirs'  => array(),
							);
						}

						// add the directory if it does not exist atm in the main folder list.
						if ( ! empty( $last_dir ) && ! isset( $folders[ $last_dir ]['dirs'][ $index ] ) ) {
							// add the directory to the list.
							$folders[ $last_dir ]['dirs'][ $index ] = array(
								'title' => $dir,
								'files' => array(),
								'dirs'  => array(),
							);
						}

						// mark this dir as last dir for file path.
						$last_dir = $index;
					}
				} else {
					// simply add the entry to the list if no directory data exist.
					$listing['files'][] = $entry;
				}
			}

			// return the resulting file list.
			return array_merge( array( 'completed' => true ), array( $this->get_directory() => $listing ), $folders );
		} catch ( S3Exception $e ) {
			// create error object.
			$error = new WP_Error();
			/* translators: %1$d will be replaced by an HTTP-status code like 403. */
			$error->add( 'efml_service_s3', sprintf( __( 'Credentials and/or bucket are not valid. The S3 returns with HTTP-Status %1$d!', 'external-files-from-aws-s3' ), $e->getStatusCode() ) );

			// add it to the list.
			$this->add_error( $error );

			return array();
		}
	}

	/**
	 * Remove the authorization header for requests on public S3 files as their usage
	 * would result in HTTP status 400 from S3.
	 *
	 * @param array<string,mixed>                                      $args The arguments.
	 * @param \ExternalFilesInMediaLibrary\ExternalFiles\Protocol_Base $http The used protocol.
	 *
	 * @return array<string,mixed>
	 */
	public function remove_authorization_header( array $args, \ExternalFilesInMediaLibrary\ExternalFiles\Protocol_Base $http ): array {
		// remove the authorization header.
		unset( $args['headers']['Authorization'] );

		// remove resulting arguments.
		return $args;
	}

	/**
	 * Return the directory.
	 *
	 * @return string
	 */
	public function get_directory(): string {
		return parent::get_directory();
	}

	/**
	 * Return the fields.
	 *
	 * @return array<string,mixed>
	 */
	public function get_fields(): array {
		return parent::get_fields();
	}

	/**
	 * Return global actions.
	 *
	 * @return array<int,array<string,string>>
	 */
	protected function get_global_actions(): array {
		return parent::get_global_actions();
	}
}
