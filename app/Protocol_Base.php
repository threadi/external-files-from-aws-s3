<?php
/**
 * File for base object, which handles the S3 support as own protocol.
 *
 * @package external-files-from-aws-s3
 */

namespace ExternalFilesFromAwsS3;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use Aws\Exception\AwsException;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use Exception;
use ExternalFilesInMediaLibrary\ExternalFiles\File;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\ExternalFiles\Import;
use ExternalFilesInMediaLibrary\ExternalFiles\Protocols\Http;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Log;
use ExternalFilesInMediaLibrary\Plugin\Settings;
use WP_Filesystem_Base;

/**
 * Object to handle different protocols.
 */
class Protocol_Base extends \ExternalFilesInMediaLibrary\ExternalFiles\Protocol_Base {
	/**
	 * The internal protocol name.
	 *
	 * @var string
	 */
	protected string $name = '';

	/**
	 * Return whether the file using this protocol is available.
	 *
	 * @return bool
	 */
	public function is_available(): bool {
		return true;
	}

	/**
	 * Check if URL is compatible with the given protocol.
	 *
	 * @return bool
	 */
	public function is_url_compatible(): bool {
		return false;
	}

	/**
	 * Check format of given URL.
	 *
	 * @param string $url The URL to check.
	 *
	 * @return bool
	 */
	public function check_url( string $url ): bool {
		// bail if empty URL is given.
		if ( empty( $url ) ) {
			return false;
		}

		// return true as URLs of this protocol are available.
		return true;
	}

	/**
	 * Return infos about single given URL.
	 *
	 * @param string $url The URL to check.
	 *
	 * @return array<string,mixed>
	 */
	public function get_url_info( string $url ): array {
		// get the HTTP protocol handler.
		$http_protocol_handler = new Http( $url );
		$http_protocol_handler->set_fields( $this->get_fields() );

		// add a filter.
		add_filter( 'efml_check_url_before', array( $this, 'replace_spaces' ) );

		// return the results from the HTTP handler.
		$result = $http_protocol_handler->get_url_info( $url );

		// revert the filter.
		remove_filter( 'efml_check_url_before', array( $this, 'replace_spaces' ) );

		// return the resulting URL info.
		return $result;
	}

	/**
	 * Return temp file from given URL.
	 *
	 * @param string             $url The given URL.
	 * @param WP_Filesystem_Base $filesystem The file system handler.
	 *
	 * @return bool|string
	 */
	public function get_temp_file( string $url, WP_Filesystem_Base $filesystem ): bool|string {
		// load private files via the S3 client, as they are not public available.
		$external_file_obj = Files::get_instance()->get_file_by_url( $url );
		if ( $external_file_obj instanceof File && method_exists( $external_file_obj, 'is_private' ) && $external_file_obj->is_private() ) {
			return $this->get_private_temp_file( $url, $external_file_obj );
		}

		// get the HTTP protocol handler.
		$http_protocol_handler = new Http( $url );
		$http_protocol_handler->set_fields( $this->get_fields() );

		// return the results from the HTTP handler.
		return $http_protocol_handler->get_temp_file( $url, $filesystem );
	}

	/**
	 * Return a temporary file for a private file, loaded via the S3 client.
	 *
	 * @since 2.0.0 Available since 2.0.0.
	 *
	 * @param string $url               The URL of the file.
	 * @param File   $external_file_obj The external file object.
	 *
	 * @return bool|string
	 */
	private function get_private_temp_file( string $url, File $external_file_obj ): bool|string {
		// get our own service object.
		$s3 = $this->get_directory_listing_object();

		// bail if no "Platform_Base" object could be loaded.
		if ( ! $s3 instanceof Platform_Base ) {
			return false;
		}

		// set the fields.
		$s3->set_fields( $this->get_fields() );

		// get the key of the file.
		$key = $this->get_object_key( $url, $external_file_obj );

		// bail if no key could be found.
		if ( '' === $key ) {
			return false;
		}

		// get the tmp file name.
		$tmp_file = wp_tempnam();

		// set the query for the file and save it in the tmp file.
		$query = array(
			'Bucket' => $s3->get_bucket_name(),
			'Key'    => $key,
			'SaveAs' => $tmp_file,
		);

		// load the file.
		try {
			$s3->get_s3_client()->getObject( $query );
		} catch ( AwsException | Exception $e ) {
			// log this event.
			Log::get_instance()->create( __( 'Private file could not be loaded from S3. Error:', 'external-files-from-aws-s3' ) . ' <code>' . esc_html( $e->getMessage() ) . '</code>', $url, 'error' );

			// delete the tmp file.
			wp_delete_file( $tmp_file );

			// return false as we do not get the file.
			return false;
		}

		// return the path to the tmp file.
		return $tmp_file;
	}

	/**
	 * Return a pre-signed URL for a private file, valid in the given time range.
	 *
	 * The signature is created locally without a request to the S3 API. For the same time range the same URL is
	 * created, so browsers and caches can reuse it.
	 *
	 * @since 2.0.0 Available since 2.0.0.
	 *
	 * @param string $url     The URL of the file (as saved on the external file).
	 * @param int    $start   The start of the validity as timestamp.
	 * @param int    $expires The end of the validity as timestamp.
	 *
	 * @return string
	 */
	public function get_signed_url( string $url, int $start, int $expires ): string {
		// bail if URL is empty or the time range is not valid.
		if ( empty( $url ) || $expires <= $start ) {
			return '';
		}

		// get our own service object.
		$s3 = $this->get_directory_listing_object();

		// bail if no "Platform_Base" object could be loaded.
		if ( ! $s3 instanceof Platform_Base ) {
			return '';
		}

		// set the fields.
		$s3->set_fields( $this->get_fields() );

		// get the key of the file.
		$key = $this->get_object_key( $url, Files::get_instance()->get_file_by_url( $url ) );

		// bail if no key could be found.
		if ( '' === $key ) {
			return '';
		}

		// create the pre-signed URL.
		try {
			// get the S3 client.
			$s3_client = $s3->get_s3_client();

			// create the command to get the file.
			$command = $s3_client->getCommand(
				'GetObject',
				array(
					'Bucket' => $s3->get_bucket_name(),
					'Key'    => $key,
				)
			);

			// create the pre-signed request for the given time range.
			$request = $s3_client->createPresignedRequest( $command, $expires, array( 'start_time' => $start ) );
		} catch ( AwsException | Exception $e ) {
			// log this event.
			Log::get_instance()->create( __( 'Pre-signed URL for private file could not be created. Error:', 'external-files-from-aws-s3' ) . ' <code>' . esc_html( $e->getMessage() ) . '</code>', $url, 'error' );

			// return empty string to use the cache instead.
			return '';
		}

		// get the URL.
		$signed_url = (string) $request->getUri();

		$instance = $this;
		/**
		 * Filter the pre-signed URL for a private file.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 * @param string $signed_url The pre-signed URL.
		 * @param string $key The key of the file.
		 * @param Protocol_Base $instance The protocol object.
		 */
		return (string) apply_filters( 'efmlawss3_signed_url', $signed_url, $key, $instance );
	}

	/**
	 * Return the key of the object for the given URL.
	 *
	 * Uses the key saved during the export, if the file is known, otherwise the key from the URL.
	 *
	 * @since 2.0.0 Available since 2.0.0.
	 *
	 * @param string     $url               The URL of the file.
	 * @param File|false $external_file_obj The external file object, if known.
	 *
	 * @return string
	 */
	protected function get_object_key( string $url, File|false $external_file_obj ): string {
		// use the saved key, if available.
		if ( $external_file_obj instanceof File ) {
			$key = get_post_meta( $external_file_obj->get_id(), 'efml_s3_key', true );
			if ( is_string( $key ) && '' !== $key ) {
				return $key;
			}
		}

		// return the key from the URL.
		return rawurldecode( $this->get_key_of_file( $url ) );
	}

	/**
	 * Return the corresponding "Service_Base" object.
	 *
	 * @return Platform_Base|false
	 */
	protected function get_directory_listing_object(): Platform_Base|false {
		return false;
	}

	/**
	 * Return whether this protocol could list the files of a directory page by page.
	 *
	 * @return bool
	 */
	public function supports_file_list(): bool {
		return true;
	}

	/**
	 * Return the requested path in the bucket of the URL of this object (e.g. a directory or a file).
	 *
	 * @return string
	 */
	private function get_requested_path(): string {
		// get our own service object.
		$s3 = $this->get_directory_listing_object();

		// bail if no "Platform_Base" object could be loaded.
		if ( ! $s3 instanceof Platform_Base ) {
			return '';
		}

		// return the requested path.
		return $s3->get_requested_url( $this->get_url(), $this->get_fields() );
	}

	/**
	 * Return whether the URL of this object is a directory in the bucket.
	 *
	 * Like for the import, a URL without a file extension is a directory.
	 *
	 * @return bool
	 */
	public function is_directory(): bool {
		// bail if no "Platform_Base" object could be loaded.
		if ( ! $this->get_directory_listing_object() instanceof Platform_Base ) {
			return false;
		}

		// get the mime type of the requested path.
		$mime_type = wp_check_filetype( basename( $this->get_requested_path() ) );

		// return whether it has no file extension.
		return false === $mime_type['ext'];
	}

	/**
	 * Return one page of the files in the requested directory of the bucket, also from its subdirectories.
	 *
	 * @param string $cursor The cursor for the requested page.
	 *
	 * @return array{files: array<int,array{url: string, media-url?: string, filesize?: int, last-modified?: int}>, cursor: string}|false
	 */
	public function get_file_list( string $cursor ): array|false {
		// get our own service object.
		$s3 = $this->get_directory_listing_object();

		// bail if no "Platform_Base" object could be loaded.
		if ( ! $s3 instanceof Platform_Base ) {
			return false;
		}

		// set the fields.
		$s3->set_fields( $this->get_fields() );

		// return the page.
		return $s3->get_file_list_page( $this->get_requested_path(), $cursor, $this->get_file_list_page_size() );
	}

	/**
	 * Return infos to each given URL.
	 *
	 * @return array<int|string,array<string,mixed>|bool> List of files with its infos.
	 */
	public function get_url_infos(): array {
		// get fields.
		$fields = $this->get_fields();

		// get our own service object.
		$s3 = $this->get_directory_listing_object();

		// bail if no "Platform_Base" object could be loaded.
		if ( ! $s3 instanceof Platform_Base ) {
			return array();
		}

		// set the fields.
		$s3->set_fields( $fields );

		// remove the domain from URL to get the Key (aka path to the file in S3).
		$url = $s3->get_requested_url( $this->get_url(), $fields );

		// get the S3Client.
		$s3_client = $s3->get_s3_client();

		// get list of directories and files in given bucket.
		try {
			// get mime type.
			$mime_type = wp_check_filetype( basename( $url ) );

			// list of files.
			$results = array();

			// get the "WP_Filesystem".
			$wp_filesystem = Helper::get_wp_filesystem();

			// if mime type check results with false values, it is a directory.
			if ( ! empty( $mime_type ) && false === $mime_type['ext'] ) { // @phpstan-ignore empty.variable
				// use the directory listing to get the dirs and files.
				$files = $s3->get_directory_listing( '/' );

				/**
				 * Run action if we have files to check via S3-protocol.
				 *
				 * @since 1.0.0 Available since 1.0.0.
				 *
				 * @param string $url   The URL to import.
				 * @param array<int|string,mixed> $files List of matches (the URLs).
				 */
				do_action( 'efmlawss3_directory_import_files', $url, $files );

				// loop through all dirs and get infos about its files.
				foreach ( $files as $dir => $dir_data ) {
					$dir = (string) $dir;
					/**
					 * Run action just before the file check via S3-protocol.
					 *
					 * @since 1.0.0 Available since 1.0.0.
					 *
					 * @param string $file_url   The URL to import.
					 */
					do_action( 'efmlawss3_directory_import_file_check', $dir );

					// bail if files is empty.
					if ( empty( $dir_data['files'] ) ) {
						continue;
					}

					// add each file to the list.
					foreach ( $dir_data['files'] as $file ) {
						// bail if this an AJAX-request, and the file already exist in media library.
						if ( wp_doing_ajax() && Files::get_instance()->get_file_by_title( $file['title'] ) ) {
							continue;
						}

						// create the array for the file data.
						$entry = array(
							'title'         => basename( $file['file'] ),
							'local'         => ! $s3->is_file_public_available( $file['s3_key'], $s3_client ),
							'url'           => $file['file'],
							'last-modified' => $file['last-modified'],
							'filesize'      => $file['filesize'],
							'mime-type'     => $file['mime-type'],
						);

						// check for duplicate.
						if ( $this->is_duplicate( $file['file'] ) ) {
							continue;
						}

						// get mime type.
						$mime_type = wp_check_filetype( $file['title'] );

						// get the tmp file name.
						$tmp_file_name = wp_tempnam();

						// generate tmp file path.
						$tmp_file = str_replace( '.tmp', '', $tmp_file_name . '.' . $mime_type['ext'] );

						// delete the tmp file name.
						$wp_filesystem->delete( $tmp_file_name );

						// set query for the file and save it in tmp dir.
						$query = array(
							'Bucket' => $this->get_fields()['bucket']['value'],
							'Key'    => $file['s3_key'],
							'SaveAs' => $tmp_file,
						);

						/**
						 * Filter the query to save a file from S3.
						 *
						 * @since 1.0.0 Available since 1.0.0.
						 * @param array<string,mixed> $query The query.
						 * @param S3Client $s3_client The S3 Client.
						 */
						$query = apply_filters( 'efmlawss3_file_import_query', $query, $s3_client );

						// try to load the requested bucket to save the tmp file.
						$s3_client->getObject( $query );

						// add tmp file to the list.
						$entry['tmp-file'] = $tmp_file;

						// add entry to the list of files.
						$results[] = $entry;
					}
				}
			} else {
				// get tmp file name.
				$tmp_file_name = wp_tempnam();

				// generate tmp file path.
				$tmp_file = str_replace( '.tmp', '', $tmp_file_name . '.' . $mime_type['ext'] );

				// delete the tmp file as we create it new via S3Client->getObject().
				$wp_filesystem->delete( $tmp_file_name );

				// get the key from given URL.
				$key = rawurldecode( $this->get_key_of_file( $this->get_url() ) );

				// set query for the file and save it in tmp dir.
				$query = array(
					'Bucket' => $this->get_fields()['bucket']['value'],
					'Key'    => $key,
					'SaveAs' => $tmp_file,
				);

				/**
				 * Filter the query to save a file from S3.
				 *
				 * @since 1.0.0 Available since 1.0.0.
				 * @param array<string,mixed> $query The query.
				 * @param S3Client $s3_client The S3 Client.
				 */
				$query = apply_filters( 'efmlawss3_file_import_query', $query, $s3_client );

				// try to load the requested bucket.
				$result = $s3_client->getObject( $query );

				// log the complete response for debugging.
				Log::get_instance()->create( __( 'Response from S3 for this file:', 'external-files-from-aws-s3' ) . ' <code>' . Helper::get_json( $result->toArray() ) . '</code>', $this->get_url(), 'info', 2, Import::get_instance()->get_identifier() );

				// check the public permissions.
				$public_access_allowed = $s3->is_file_public_available( $key, $s3_client );

				// get the URL depending on the permissions.
				$url = $public_access_allowed ? $s3->get_public_url_of_file( $key, $fields ) : $this->get_url();

				// get the mime type from the response.
				$mime_type_from_response = (string) $result->get( 'ContentType' );

				// fall back to the file extension if the storage returns a generic type.
				// Some S3-compatible platforms, e.g. Cloudflare R2, store objects as
				// "application/octet-stream" if no type has been set during the upload.
				if ( in_array( $mime_type_from_response, array( '', 'application/octet-stream', 'application/binary', 'binary/octet-stream' ), true ) ) {
					$mime_type_by_name = wp_check_filetype( basename( $key ) );

					// get the detected file type.
					if ( ! empty( $mime_type_by_name['type'] ) ) {
						$mime_type_from_response = $mime_type_by_name['type'];
					}
				}

				// create the array for the file data.
				$entry = array(
					'title'         => basename( $url ),
					'local'         => ! $public_access_allowed,
					'url'           => $url,
					'last-modified' => Helper::get_format_date_time( gmdate( 'Y-m-d H:i:s', absint( $result->get( 'LastModified' )->format( 'U' ) ) ) ),
					'filesize'      => $result->get( 'ContentLength' ),
					'mime-type'     => $mime_type_from_response,
					'tmp-file'      => $tmp_file,
				);

				// check for duplicate.
				if ( $this->is_duplicate( $url ) ) {
					$wp_filesystem->delete( $tmp_file );
					return array();
				}

				// add entry to the list of files.
				$results[] = $entry;
			}
			return $results;
		} catch ( S3Exception $e ) {
			/* translators: %1$s will be replaced by a URL. */
			Log::get_instance()->create( sprintf( __( 'Error during request of S3 file. <a href="%1$s">Check the log</a> for details.', 'external-files-from-aws-s3' ), esc_url( Settings::get_instance()->get_url( 'eml_logs' ) ) ), $this->get_url(), 'error', 0, Import::get_instance()->get_identifier() );
			Log::get_instance()->create( __( 'Error during request of S3 file:', 'external-files-from-aws-s3' ) . ' <code>' . $e->getMessage() . '</code>', $this->get_url(), 'error' );
			return array();
		}
	}

	/**
	 * Return whether the file should be saved local (true) or not (false).
	 *
	 * @return bool
	 */
	public function should_be_saved_local(): bool {
		return true;
	}

	/**
	 * Return whether this URL could change its hosting.
	 *
	 * @return bool
	 */
	public function can_change_hosting(): bool {
		return false;
	}

	/**
	 * Return whether this URL could be checked for availability.
	 *
	 * @return bool
	 */
	public function can_check_availability(): bool {
		return false;
	}

	/**
	 * Return whether URLs with this protocol are reachable via HTTP.
	 *
	 * This is not the availability of the URL.
	 *
	 * @return bool
	 */
	public function is_url_reachable(): bool {
		return false;
	}

	/**
	 * Return the key of a file by given URL.
	 *
	 * @param string $url The URL.
	 *
	 * @return string
	 */
	protected function get_key_of_file( string $url ): string {
		return $url;
	}

	/**
	 * Replace spaces in URL before we check it.
	 *
	 * @param string $url The URL.
	 *
	 * @return string
	 */
	public function replace_spaces( string $url ): string {
		return str_replace( ' ', '%20', $url );
	}

	/**
	 * Run check for duplicates for a given URL:
	 *
	 * @param string $url The given URL.
	 *
	 * @return bool
	 */
	private function is_duplicate( string $url ): bool {
		// check for duplicate.
		if ( ! $this->check_for_duplicate( $url ) ) {
			return false;
		}

		// log this event.
		Log::get_instance()->create( __( 'Specified URL already exist in your media library.', 'external-files-from-aws-s3' ), $url, 'error', 0, Import::get_instance()->get_identifier() );

		// return true as duplicate has been found.
		return true;
	}
}
