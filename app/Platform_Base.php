<?php
/**
 * File to handle the Cloudflare R2 support as directory listing.
 *
 * @package external-files-from-aws-s3
 */

namespace ExternalFilesFromAwsS3;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use Aws\Exception\AwsException;
use Aws\CommandInterface;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use easySettingsForWordPress\Section;
use ExternalFilesInMediaLibrary\ExternalFiles\File;
use ExternalFilesInMediaLibrary\ExternalFiles\ImportDialog;
use ExternalFilesInMediaLibrary\Plugin\Admin\Directory_Listing;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Log;
use ExternalFilesInMediaLibrary\Services\Service_Base;
use Exception;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;
use WP_Error;
use WP_User;

/**
 * Object for handle tasks for each platform object.
 */
class Platform_Base extends Service_Base {
	/**
	 * Whether failed checks of the public availability should be logged with all details.
	 *
	 * Enabled during the export and the connection test, as these checks are expected to be successful.
	 *
	 * @var bool
	 */
	protected bool $log_public_check_failures = false;

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
	 * Allow to define the values of the fields via constants, e.g. "EFML_AWS_S3_ACCESS_KEY".
	 *
	 * Requires "External Files in Media Library" 6.0.0 or newer.
	 *
	 * @var bool
	 */
	protected bool $fields_via_constants = true;

	/**
	 * List of fields, which identify the bucket of this platform.
	 *
	 * If the credentials are defined via constants, saved external sources for other buckets could not be used,
	 * and the constants are never used for them.
	 *
	 * Requires "External Files in Media Library" 6.0.0 or newer.
	 *
	 * @var array<int,string>
	 */
	protected array $constants_target_fields = array( 'bucket' );

	/**
	 * Initialize this object.
	 *
	 * @return void
	 */
	public function init(): void {
		// use parent initialization.
		parent::init();

		// bail if user has no capability for this service.
		if ( ! defined( 'DOING_CRON' ) && ! Helper::is_cli() && ! current_user_can( 'efml_cap_' . $this->get_name() ) ) {
			return;
		}

		// use our own hooks.
		add_filter( 'efml_protocols', array( $this, 'add_protocol' ) );
		add_filter( 'efml_directory_listing', array( $this, 'prepare_tree_building' ), 10, 3 );
		add_filter( 'efml_http_header_args', array( $this, 'remove_authorization_header' ), 10, 2 );
		add_action( 'efml_after_file_save', array( $this, 'change_service_name' ) );

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
		// loop through the tree.
		foreach ( $tree as $key => $value ) {
			// bail if directory has been found.
			if ( $key === $directory ) {
				return array( $key => $value );
			}

			// bail if this entry does not have any sub-directories.
			if ( empty( $value['dirs'] ) || ! is_array( $value['dirs'] ) ) {
				continue;
			}

			// check the deeper tree entries.
			$real_tree = $this->get_real_tree( $value['dirs'], $directory );

			// return the result if the directory has been found there.
			if ( ! empty( $real_tree ) ) {
				return $real_tree;
			}
		}

		// return empty array as the directory has not been found.
		return array();
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
		// Hint: the longest URL must be removed first, as the URL mark without bucket is part of the URL with bucket.
		$directory = str_replace(
			array(
				$this->get_directory(),
				$this->get_url_mark( $this->get_bucket_name() ),
				$this->get_url_mark( '' ),
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

		?><h3 id="efml-<?php echo esc_attr( $this->get_name() ); ?>"><?php echo esc_html( $this->get_label() ); ?></h3>
		<div class="efml-user-settings">
			<?php

			// show settings table.
			$this->get_user_settings_table( absint( $user->ID ) );

			?>
		</div>
		<?php
	}

	/**
	 * Show a non-editable hint instead of a setting, if the value of the given field is defined via constant.
	 *
	 * @param Section $section    The section for the setting.
	 * @param string  $field_name The name of the field.
	 * @param string  $title      The title of the setting.
	 *
	 * @return bool True if the hint has been added, false if the setting should be added.
	 */
	protected function show_constant_hint( Section $section, string $field_name, string $title ): bool {
		// bail if the main plugin does not support this (requires "External Files in Media Library" 6.0.0 or newer).
		if ( ! method_exists( $this, 'add_constant_hint_setting' ) ) { // @phpstan-ignore function.alreadyNarrowedType
			return false;
		}

		// add the hint, if the value is defined via constant.
		return $this->add_constant_hint_setting( $section, $field_name, $title );
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

			// filter the query.
			$query = $this->filter_query_params( $query, $directory );

			/**
			 * Get list of files.
			 *
			 * This will be all files in the complete bucket incl. subdirectories. S3 returns at most 1000 files
			 * per request, so all parts of the list are loaded one after another.
			 */
			$files = $this->get_all_objects( $s3, $query );

			// bail if no data returned.
			if ( empty( $files ) ) {
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
				// bail if this file should be hidden.
				if ( $this->is_file_hidden( $file, $directory ) ) {
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
							$folders[ $last_dir ]['files'][] = $entry; // @phpstan-ignore offsetAssign.dimType
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
						if ( ! empty( $last_dir ) && ! isset( $folders[ $last_dir ]['dirs'][ $index ] ) ) { // @phpstan-ignore isset.offset
							// add the directory to the list.
							$folders[ $last_dir ]['dirs'][ $index ] = array( // @phpstan-ignore offsetAssign.dimType
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
	 * Return one page of the files in the bucket for the import of a directory, without loading the files.
	 *
	 * Contains the same files as the directory listing: also the files in subdirectories, without hidden files
	 * and files with not allowed file types.
	 *
	 * @since 2.0.0 Available since 2.0.0.
	 *
	 * @param string $prefix    The prefix of the keys (e.g. the requested directory), empty for the complete bucket.
	 * @param string $cursor    The continuation token of the page, empty for the first page.
	 * @param int    $page_size The maximum count of objects on this page.
	 *
	 * @return array{files: array<int,array{url: string, media-url?: string, filesize?: int, last-modified?: int}>, cursor: string}|false
	 */
	public function get_file_list_page( string $prefix, string $cursor, int $page_size ): array|false {
		// get the S3Client.
		$s3 = $this->get_s3_client();

		// create the query.
		$query = $this->filter_query_params( array( 'Bucket' => $this->get_bucket_name() ), '/' );

		// use the prefix, if the query does not have one and it is a path in the bucket (not a URL).
		if ( empty( $query['Prefix'] ) && '' !== $prefix && '/' !== $prefix && ! str_contains( $prefix, '://' ) ) {
			$query['Prefix'] = ltrim( $prefix, '/' );
		}

		// set the size of the page.
		$query['MaxKeys'] = $page_size;

		// set the token for the requested page.
		if ( '' !== $cursor ) {
			$query['ContinuationToken'] = $cursor;
		}

		// load the page.
		try {
			$result = $s3->listObjectsV2( $query );
		} catch ( S3Exception $e ) {
			/* translators: %1$d will be replaced by an HTTP-status code like 403. */
			Log::get_instance()->create( sprintf( __( 'Credentials and/or bucket are not valid. The S3 returns with HTTP-Status %1$d!', 'external-files-from-aws-s3' ), $e->getStatusCode() ) . ' <code>' . esc_html( $e->getMessage() ) . '</code>', $this->get_directory(), 'error' );
			return false;
		}

		// collect the files.
		$files = array();
		foreach ( is_array( $result['Contents'] ) ? $result['Contents'] : array() as $object ) {
			// bail if this is a directory.
			if ( str_ends_with( (string) $object['Key'], '/' ) ) {
				continue;
			}

			// bail if this file should be hidden.
			if ( $this->is_file_hidden( $object, '/' ) ) {
				continue;
			}

			// bail if file type is not allowed.
			$mime_type = wp_check_filetype( basename( (string) $object['Key'] ) );
			if ( empty( $mime_type['type'] ) ) {
				continue;
			}

			// add the file.
			$files[] = array(
				'url'           => $this->get_public_url_of_file( (string) $object['Key'], $this->get_fields() ),
				'filesize'      => absint( $object['Size'] ),
				'last-modified' => absint( $object['LastModified']->format( 'U' ) ),
			);
		}

		// get the token for the next page, if the list is not complete (and the server returns a new one).
		$token = ! empty( $result['IsTruncated'] ) && ! empty( $result['NextContinuationToken'] ) ? (string) $result['NextContinuationToken'] : '';
		if ( $token === $cursor ) {
			$token = '';
		}

		// return the page.
		return array(
			'files'  => $files,
			'cursor' => $token,
		);
	}

	/**
	 * Return all objects for the given query, also if S3 returns them in multiple parts.
	 *
	 * S3 returns at most 1000 objects per request (or the value of "MaxKeys"). The following parts are loaded
	 * with the continuation token of the previous part until the list is complete.
	 *
	 * @since 2.0.0 Available since 2.0.0.
	 *
	 * @param S3Client            $s3 The S3 client.
	 * @param array<string,mixed> $query The query for "ListObjectsV2".
	 *
	 * @return array<int,array<string,mixed>>
	 * @throws S3Exception If a request fails.
	 */
	protected function get_all_objects( S3Client $s3, array $query ): array {
		// collect the objects.
		$objects = array();

		// collect the used tokens to prevent endless loops with faulty responses.
		$used_tokens = array();

		do {
			// load the actual part.
			$result = $s3->listObjectsV2( $query );

			// add its objects to the list.
			if ( is_array( $result['Contents'] ) ) {
				foreach ( $result['Contents'] as $object ) {
					$objects[] = $object;
				}
			}

			// get the token for the next part, if the list is not complete.
			$token = ! empty( $result['IsTruncated'] ) && ! empty( $result['NextContinuationToken'] ) ? (string) $result['NextContinuationToken'] : '';

			// bail if this token has been used already.
			if ( isset( $used_tokens[ $token ] ) ) {
				break;
			}
			$used_tokens[ $token ] = true;

			// use the token for the next part.
			$query['ContinuationToken'] = $token;
		} while ( '' !== $token );

		// return the complete list.
		return $objects;
	}

	/**
	 * Return the filtered query for the list of files in the bucket.
	 *
	 * Platforms could override this to use their own hook.
	 *
	 * @param array<string,mixed> $query The query.
	 * @param string              $directory The requested directory.
	 *
	 * @return array<string,mixed>
	 */
	protected function filter_query_params( array $query, string $directory ): array {
		/**
		 * Filter the query for files in AWS S3.
		 *
		 * @since 1.0.0 Available since 1.0.0.
		 * @param array $query The query.
		 * @param string $directory The URL.
		 */
		return apply_filters( 'efmlawss3_aws_s3_query_params', $query, $directory );
	}

	/**
	 * Return whether the given file from the bucket should be hidden in the directory listing.
	 *
	 * Platforms could override this to use their own hook.
	 *
	 * @param array<string,mixed> $file The array with the file data.
	 * @param string              $directory The requested directory.
	 *
	 * @return bool
	 */
	protected function is_file_hidden( array $file, string $directory ): bool {
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
		return (bool) apply_filters( 'efmlawss3_service_aws_s3_hide_file', $false, $file, $directory );
	}

	/**
	 * Return the custom domain (e.g. a CDN) from the given fields, without scheme and trailing slash.
	 *
	 * @since 2.0.0 Available since 2.0.0.
	 *
	 * @param array<string,mixed> $fields The fields.
	 *
	 * @return string Empty string, if no custom domain is set.
	 */
	public function get_custom_domain( array $fields ): string {
		// get the value.
		$domain = ! empty( $fields['public_domain']['value'] ) && is_string( $fields['public_domain']['value'] ) ? trim( $fields['public_domain']['value'] ) : '';

		// remove the scheme and the trailing slash.
		$domain = (string) preg_replace( '/^https?:\/\//i', '', $domain );

		// return the domain.
		return rtrim( $domain, '/' );
	}

	/**
	 * Return the URL of the given file on the given custom domain.
	 *
	 * @since 2.0.0 Available since 2.0.0.
	 *
	 * @param string $domain The custom domain (without scheme).
	 * @param string $key    The key of the file.
	 *
	 * @return string
	 */
	public function get_custom_domain_url( string $domain, string $key ): string {
		// encode each part of the path, keeping "/" as separator.
		$encoded_key = implode( '/', array_map( 'rawurlencode', explode( '/', ltrim( $key, '/' ) ) ) );

		// return the URL.
		return 'https://' . $domain . '/' . $encoded_key;
	}

	/**
	 * Return the key of the file of the given URL on the custom domain of the given fields.
	 *
	 * @since 2.0.0 Available since 2.0.0.
	 *
	 * @param string              $url    The URL.
	 * @param array<string,mixed> $fields The fields.
	 *
	 * @return string Empty string, if the URL is not on the custom domain.
	 */
	public function get_key_from_custom_domain_url( string $url, array $fields ): string {
		// get the custom domain.
		$domain = $this->get_custom_domain( $fields );

		// bail if no custom domain is set.
		if ( '' === $domain ) {
			return '';
		}

		// check both schemes.
		foreach ( array( 'https://' . $domain . '/', 'http://' . $domain . '/' ) as $prefix ) {
			if ( str_starts_with( $url, $prefix ) ) {
				return rawurldecode( substr( $url, strlen( $prefix ) ) );
			}
		}

		// return nothing as the URL is not on the custom domain.
		return '';
	}

	/**
	 * Return whether this platform supports the complete connection test of External Files in Media Library.
	 *
	 * @since 2.0.0 Available since 2.0.0.
	 *
	 * @return bool
	 */
	public function supports_connection_test(): bool {
		return true;
	}

	/**
	 * Return whether the connection is configured enough to test it.
	 *
	 * Platforms could override this, e.g. to check their endpoint, too.
	 *
	 * @return bool
	 */
	protected function is_connection_configured(): bool {
		return '' !== $this->get_bucket_name();
	}

	/**
	 * Return a hint for the given HTTP status and S3 error code.
	 *
	 * Platforms could override this to give hints for their own settings.
	 *
	 * @param int    $status     The HTTP status.
	 * @param string $error_code The S3 error code.
	 *
	 * @return string
	 */
	public function get_hint_for_error( int $status, string $error_code ): string {
		// the storage could not be reached.
		if ( 0 === $status ) {
			return __( 'Check whether your hosting is allowed to connect to the storage.', 'external-files-from-aws-s3' );
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
				return __( 'Check the name and the region of the bucket.', 'external-files-from-aws-s3' );
			case 'AccessDenied':
				return __( 'The access key is not allowed to do this. Check its permissions for the bucket.', 'external-files-from-aws-s3' );
		}

		// check some HTTP status without error code.
		if ( 301 === $status || 400 === $status ) {
			return __( 'Check the region of the bucket.', 'external-files-from-aws-s3' );
		}
		if ( 403 === $status ) {
			return __( 'Check the credentials and the permissions of the access key.', 'external-files-from-aws-s3' );
		}
		if ( 404 === $status ) {
			return __( 'Check the name and the region of the bucket.', 'external-files-from-aws-s3' );
		}

		// return a general hint.
		return __( 'Please contact the provider of your storage with the details from the log.', 'external-files-from-aws-s3' );
	}

	/**
	 * Add the logging of failed requests to the given S3 client.
	 *
	 * Each failed request is logged with all details via @log_aws_exception().
	 *
	 * @since 2.0.0 Available since 2.0.0.
	 *
	 * @param S3Client $client The S3 client.
	 *
	 * @return S3Client
	 */
	protected function add_error_log( S3Client $client ): S3Client {
		// add the middleware only once.
		$client->getHandlerList()->remove( 'efmlawss3_error_log' );
		$client->getHandlerList()->appendInit( array( $this, 'get_error_log_middleware' ), 'efmlawss3_error_log' );

		// return the client.
		return $client;
	}

	/**
	 * Return a text for the given exception, which explains the error and a possible solution.
	 *
	 * @param AwsException $e The exception.
	 *
	 * @return string
	 */
	public function get_error_text_for_exception( AwsException $e ): string {
		// get the status and the error code.
		$status     = absint( $e->getStatusCode() );
		$error_code = (string) $e->getAwsErrorCode();

		// get the text with the error.
		if ( 0 === $status ) {
			$text = __( 'The storage could not be reached.', 'external-files-from-aws-s3' );
		} else {
			/* translators: %1$d will be replaced by an HTTP status like 403, %2$s by an error code like "AccessDenied". */
			$text = sprintf( __( 'The storage returns with HTTP status %1$d and the error code %2$s.', 'external-files-from-aws-s3' ), $status, '' !== $error_code ? $error_code : '-' );
		}

		// return the text with the hint.
		return $text . ' ' . $this->get_hint_for_error( $status, $error_code ) . ' ' . __( 'Check the log for details.', 'external-files-from-aws-s3' );
	}

	/**
	 * Return the middleware for the S3 client, which logs failed requests.
	 *
	 * @param callable $handler The next handler.
	 *
	 * @return callable
	 */
	public function get_error_log_middleware( callable $handler ): callable {
		return function ( CommandInterface $command, ?RequestInterface $request = null ) use ( $handler ): PromiseInterface {
			return $handler( $command, $request )->otherwise(
				function ( $reason ) use ( $command ) {
					// log the error, except a not existing file during a check for its existence.
					if ( $reason instanceof AwsException && ! ( 'HeadObject' === $command->getName() && 404 === $reason->getStatusCode() ) ) {
						$this->log_aws_exception( $reason, $command->getName(), $this->get_directory() );
					}

					// return the rejection, so it is handled by the caller.
					return Create::rejectionFor( $reason );
				}
			);
		};
	}

	/**
	 * Enable or disable the detailed log for failed checks of the public availability.
	 *
	 * @param bool $enabled True to enable it.
	 *
	 * @return void
	 */
	public function set_public_check_logging( bool $enabled ): void {
		$this->log_public_check_failures = $enabled;
	}

	/**
	 * Run a complete test of the connection with the actual fields.
	 *
	 * The test lists the bucket, uploads a small test file the same way an export does it,
	 * checks if it is public available and deletes it. Each step is logged.
	 *
	 * @since 2.0.0 Available since 2.0.0 (before only for S3-compatible storages).
	 *
	 * @return array<int,array<string,mixed>> List of steps with "title", "success", "skipped" and "message".
	 */
	public function run_connection_test(): array {
		// get the fields.
		$fields = $this->get_fields();

		// get the URL for the log entries.
		$log_url = $this->get_directory();

		// log the start.
		/* translators: %1$s will be replaced by the name of the platform. */
		Log::get_instance()->create( sprintf( __( 'Starting the connection test for %1$s.', 'external-files-from-aws-s3' ), '<em>' . esc_html( $this->get_label() ) . '</em>' ), $log_url, 'info' );

		// prepare the steps.
		$steps = array(
			'list'   => array(
				'title'   => __( 'Connect and list the bucket', 'external-files-from-aws-s3' ),
				'success' => false,
				'skipped' => true,
				'message' => '',
			),
			'upload' => array(
				'title'   => __( 'Upload a test file', 'external-files-from-aws-s3' ),
				'success' => false,
				'skipped' => true,
				'message' => '',
			),
			'public' => array(
				'title'   => __( 'Check the public availability of the test file', 'external-files-from-aws-s3' ),
				'success' => false,
				'skipped' => true,
				'message' => '',
			),
			'delete' => array(
				'title'   => __( 'Delete the test file', 'external-files-from-aws-s3' ),
				'success' => false,
				'skipped' => true,
				'message' => '',
			),
		);

		// bail if endpoint or bucket are missing.
		if ( ! $this->is_connection_configured() ) {
			$steps['list']['skipped'] = false;
			$steps['list']['message'] = __( 'The endpoint or the bucket is missing.', 'external-files-from-aws-s3' );
			return $this->log_connection_test( $steps, $log_url );
		}

		// get the client.
		$s3_client = $this->get_s3_client();

		// step 1: list the bucket.
		$steps['list']['skipped'] = false;
		try {
			$s3_client->listObjectsV2(
				array(
					'Bucket'  => $this->get_bucket_name(),
					'MaxKeys' => 1,
				)
			);
			$steps['list']['success'] = true;
			$steps['list']['message'] = __( 'The bucket could be listed.', 'external-files-from-aws-s3' );
		} catch ( AwsException $e ) {
			$steps['list']['message'] = $this->get_error_text_for_exception( $e );
			return $this->log_connection_test( $steps, $log_url );
		}

		// step 2: upload a test file the same way as an export.
		$key                        = $this->get_connection_test_file_name();
		$steps['upload']['skipped'] = false;
		try {
			$s3_client->upload(
				$this->get_bucket_name(),
				$key,
				__( 'This is a test file of the plugin "External files from AWS S3". It could be deleted.', 'external-files-from-aws-s3' ),
				'private',
				array(
					'params' => array(
						'ContentType' => 'text/plain',
					),
				)
			);
			$steps['upload']['success'] = true;
			/* translators: %1$s will be replaced by a file name. */
			$steps['upload']['message'] = sprintf( __( 'The test file %1$s has been uploaded.', 'external-files-from-aws-s3' ), $key );
		} catch ( AwsException $e ) {
			$steps['upload']['message'] = $this->get_error_text_for_exception( $e );
			return $this->log_connection_test( $steps, $log_url );
		} catch ( Exception $e ) {
			$steps['upload']['message'] = $e->getMessage();
			return $this->log_connection_test( $steps, $log_url );
		}

		// step 3: check the public availability.
		$steps['public']['skipped'] = false;
		$public_url                 = $this->get_public_url_of_file( $key, $fields );
		$this->set_public_check_logging( true );
		$steps['public']['success'] = $this->is_file_public_available( $key, $s3_client );
		$this->set_public_check_logging( false );
		if ( $steps['public']['success'] ) {
			/* translators: %1$s will be replaced by a URL. */
			$steps['public']['message'] = sprintf( __( 'The test file is public available under %1$s.', 'external-files-from-aws-s3' ), $public_url );
		} else {
			/* translators: %1$s will be replaced by a URL. */
			$steps['public']['message'] = sprintf( __( 'The test file is not public available under %1$s. Files could be imported and exported with the option "Save files privately", but not exported as public files. Check the permissions of the bucket or the public URL.', 'external-files-from-aws-s3' ), $public_url );
		}

		// step 4: delete the test file.
		$steps['delete']['skipped'] = false;
		try {
			$s3_client->deleteObject(
				array(
					'Bucket' => $this->get_bucket_name(),
					'Key'    => $key,
				)
			);
			$steps['delete']['success'] = true;
			$steps['delete']['message'] = __( 'The test file has been deleted.', 'external-files-from-aws-s3' );
		} catch ( AwsException $e ) {
			/* translators: %1$s will be replaced by a file name. */
			$steps['delete']['message'] = $this->get_error_text_for_exception( $e ) . ' ' . sprintf( __( 'Please delete the test file %1$s manually.', 'external-files-from-aws-s3' ), $key );
		}

		// return the result.
		return $this->log_connection_test( $steps, $log_url );
	}

	/**
	 * Log the results of the connection test and return the steps as list.
	 *
	 * @param array<string,array<string,mixed>> $steps   The steps.
	 * @param string                            $log_url The URL for the log entries.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function log_connection_test( array $steps, string $log_url ): array {
		foreach ( $steps as $step ) {
			// bail if step has been skipped.
			if ( $step['skipped'] ) {
				continue;
			}

			// log the result.
			Log::get_instance()->create( esc_html( $step['title'] ) . ': ' . esc_html( $step['message'] ), $log_url, $step['success'] ? 'success' : 'error' );
		}

		// return the steps as list.
		return array_values( $steps );
	}

	/**
	 * Return the details of the connection, which are added to log entries for failed requests.
	 *
	 * The list must never contain credentials. Platforms could override this to add their own details.
	 *
	 * @return array<string,string>
	 */
	public function get_log_context(): array {
		return array(
			__( 'Platform', 'external-files-from-aws-s3' ) => $this->get_label(),
			__( 'Bucket', 'external-files-from-aws-s3' )   => $this->get_bucket_name(),
		);
	}

	/**
	 * Add a detailed log entry for a failed request to the S3 API.
	 *
	 * The entry contains all details, which are necessary to analyze the error together with the
	 * provider of the storage: the HTTP status, the S3 error code and message, the request IDs
	 * of the provider and the connection details from @get_log_context(). Credentials are never logged.
	 *
	 * @param AwsException $e         The exception.
	 * @param string       $operation The name of the operation (e.g. "PutObject").
	 * @param string       $url       The URL the entry is assigned to (optional).
	 *
	 * @return void
	 */
	public function log_aws_exception( AwsException $e, string $operation, string $url = '' ): void {
		// collect the details of the error.
		$details = array(
			__( 'HTTP status', 'external-files-from-aws-s3' )         => (string) $e->getStatusCode(),
			__( 'Error code', 'external-files-from-aws-s3' )          => (string) $e->getAwsErrorCode(),
			__( 'Error message', 'external-files-from-aws-s3' )       => (string) $e->getAwsErrorMessage(),
			__( 'Request ID', 'external-files-from-aws-s3' )          => (string) $e->getAwsRequestId(),
			__( 'Extended request ID', 'external-files-from-aws-s3' ) => $this->get_extended_request_id( $e ),
		);

		// add the message of the exception, if no S3 error message has been returned (e.g. if the connection failed).
		if ( '' === $details[ __( 'Error message', 'external-files-from-aws-s3' ) ] ) {
			$previous = $e->getPrevious();
			$details[ __( 'Error message', 'external-files-from-aws-s3' ) ] = $previous instanceof \Throwable ? $previous->getMessage() : $e->getMessage();
		}

		// add the details of the connection.
		$details = array_merge( $details, $this->get_log_context() );

		// create the output.
		/* translators: %1$s will be replaced by the name of the operation (e.g. "PutObject"). */
		$message = sprintf( __( 'The request %1$s to the storage failed.', 'external-files-from-aws-s3' ), '<code>' . esc_html( $operation ) . '</code>' );
		foreach ( $details as $label => $value ) {
			// bail if no value is given.
			if ( '' === $value ) {
				continue;
			}

			// add the detail.
			$message .= '<br>' . esc_html( $label ) . ': <code>' . esc_html( $value ) . '</code>';
		}

		// add the log entry.
		Log::get_instance()->create( $message, $url, 'error' );
	}

	/**
	 * Return the extended request ID of the provider from the response of the given exception.
	 *
	 * @param AwsException $e The exception.
	 *
	 * @return string
	 */
	private function get_extended_request_id( AwsException $e ): string {
		// get the response.
		$response = $e->getResponse();

		// bail if no response is given (e.g. if the connection failed).
		if ( is_null( $response ) ) {
			return '';
		}

		// return the header with the extended request ID.
		return $response->getHeaderLine( 'x-amz-id-2' );
	}

	/**
	 * Remove the authorization header for requests on public S3 files as their usage
	 * would result in HTTP status 400 from S3.
	 *
	 * This is only done for URLs of this platform, as the hook is used for each HTTP request of the main plugin.
	 *
	 * @param array<string,mixed>                                      $args The arguments.
	 * @param \ExternalFilesInMediaLibrary\ExternalFiles\Protocol_Base $http The used protocol.
	 *
	 * @return array<string,mixed>
	 */
	public function remove_authorization_header( array $args, \ExternalFilesInMediaLibrary\ExternalFiles\Protocol_Base $http ): array {
		// bail if URL on protocol is not from this platform.
		if ( ! $this->is_platform_url( $http->get_url() ) ) {
			return $args;
		}

		// remove the authorization header.
		unset( $args['headers']['Authorization'] );

		// remove resulting arguments.
		return $args;
	}

	/**
	 * Return whether the given URL is a URL of this platform.
	 *
	 * @param string $url The URL to check.
	 *
	 * @return bool
	 */
	public function is_platform_url( string $url ): bool {
		return false;
	}

	/**
	 * Return whether a cache in front of the bucket (e.g., a CDN) should be invalidated after changes.
	 *
	 * @return bool
	 */
	public function is_cache_invalidation_enabled(): bool {
		return false;
	}

	/**
	 * Invalidate the given file keys in a cache in front of the bucket (e.g., a CDN).
	 *
	 * @param array<int,string> $keys List of file keys.
	 *
	 * @return bool
	 */
	public function invalidate_cache( array $keys ): bool {
		return false;
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
	 * Return the public label.
	 *
	 * Hint: helper to PHPStan to not warn about not existing functions as they are in the main plugin.
	 *
	 * @return string
	 */
	public function get_label(): string {
		return parent::get_label();
	}

	/**
	 * Return the URL of the web interface of this platform.
	 *
	 * Empty, if the platform has no such interface (e.g. S3-compatible storage with own endpoint).
	 * Also defined here to be usable with older versions of the main plugin.
	 *
	 * @since 2.0.0 Available since 2.0.0.
	 *
	 * @return string
	 */
	public function get_platform_url(): string {
		return '';
	}

	/**
	 * Return global actions.
	 *
	 * Adds the link to the web interface of the platform, if it is known.
	 *
	 * @return array<int,array<string,string>>
	 */
	protected function get_global_actions(): array {
		// get the actions of the main plugin.
		$actions = parent::get_global_actions();

		// get the URL of the platform.
		$platform_url = $this->get_platform_url();

		// add the link to the platform, if its URL is known.
		if ( '' !== $platform_url ) {
			$actions[] = array(
				'action' => 'location.href="' . esc_url( $platform_url ) . '";',
				/* translators: %1$s will be replaced by the name of the platform (e.g. Wasabi). */
				'label'  => sprintf( __( 'Go to %1$s', 'external-files-from-aws-s3' ), $this->get_label() ),
			);
		}

		// return the resulting list.
		return $actions;
	}

	/**
	 * Change the used service name if multisite import has been used.
	 *
	 * @param File $external_file_obj The external file object.
	 *
	 * @return void
	 */
	public function change_service_name( File $external_file_obj ): void {
		// get service from request.
		$service = filter_input( INPUT_POST, 'service', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		// bail if it is not set.
		if ( is_null( $service ) ) {
			return;
		}

		// bail if service is not ours.
		if ( $this->get_name() !== $service ) {
			return;
		}

		// set the service name.
		$external_file_obj->set_service_name( $service );
	}
}
