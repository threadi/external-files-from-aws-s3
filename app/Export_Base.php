<?php
/**
 * File to handle export tasks for S3-compatible platforms.
 *
 * @package external-files-from-aws-s3
 */

namespace ExternalFilesFromAwsS3;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use Aws\Exception\AwsException;
use Aws\Result;
use Exception;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Log;

/**
 * Object for export files to S3-compatible platforms.
 */
class Export_Base extends \ExternalFilesInMediaLibrary\ExternalFiles\Export_Base {
	/**
	 * Export a file to this service. Returns the external URL if it was successfully and false if not.
	 *
	 * @param int                 $attachment_id The attachment ID.
	 * @param string              $target The target.
	 * @param array<string,mixed> $credentials The credentials.
	 * @return string|bool
	 */
	public function export_file( int $attachment_id, string $target, array $credentials ): string|bool {
		// get our own service object.
		$s3_obj = $this->get_directory_listing_object();

		// bail if no "Platform_Base" object could be loaded.
		if ( ! $s3_obj instanceof Platform_Base ) {
			return false;
		}

		// set the credentials.
		$s3_obj->set_fields( isset( $credentials['fields'] ) ? $credentials['fields'] : array() );

		// bail if the given URL is not an S3 URL.
		if ( ! str_starts_with( $target, $s3_obj->get_directory() ) ) {
			// log this event.
			Log::get_instance()->create( __( 'Given path is not a S3-URL.', 'external-files-from-aws-s3' ), $target, 'error' );

			// do nothing more.
			return false;
		}

		// get the file path.
		$file_path = get_attached_file( $attachment_id, true );

		// bail if no file could be found.
		if ( ! is_string( $file_path ) ) {
			return false;
		}

		// get the local "WP_Filesystem".
		$wp_filesystem_local = Helper::get_wp_filesystem();

		// bail if source file does not exist.
		if ( ! $wp_filesystem_local->exists( $file_path ) ) {
			return false;
		}

		// get the key for this file.
		$key = $this->get_key_for_filename( $this->get_folder_from_target( $target, $credentials ) . basename( $file_path ), $credentials, $s3_obj );

		// get the S3Client.
		$s3_client = $s3_obj->get_s3_client();

		// log this event.
		/* translators: %1$s will be replaced by the attachment ID, %2$s by the bucket name. */
		Log::get_instance()->create( sprintf( __( 'Exporting attachment ID %1$s to S3 bucket %2$s.', 'external-files-from-aws-s3' ), '<em>' . $attachment_id . '</em>', '<em>' . $s3_obj->get_bucket_name() . '</em>' ), $target, 'info', 2 );

		// check whether a file with this key already exists, which would be overwritten (only if a cache must be invalidated then).
		$file_exists = false;
		if ( $s3_obj->is_cache_invalidation_enabled() ) {
			try {
				$file_exists = $s3_client->doesObjectExistV2( $s3_obj->get_bucket_name(), $key );
			} catch ( AwsException | Exception $e ) {
				// if the check fails, we assume the file exists to invalidate the cache for safety.
				$file_exists = true;
			}
		}

		// open the file as stream, so it is not loaded completely in the memory.
		// the SDK uses a multipart upload for larger files automatically.
		$handle = fopen( $file_path, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen

		// bail if file could not be opened.
		if ( ! $handle ) {
			// log this event.
			Log::get_instance()->create( __( 'File could not be opened for the export.', 'external-files-from-aws-s3' ), $target, 'error' );

			// do nothing more.
			return false;
		}

		// upload the file.
		try {
			$result = $s3_client->upload(
				$s3_obj->get_bucket_name(),
				$key,
				$handle,
				'private',
				array(
					'params' => $this->get_upload_params( $attachment_id, $file_path, $key, $s3_obj ),
				)
			);
		} catch ( AwsException | Exception $e ) {
			// log this event.
			Log::get_instance()->create( __( 'File could not be uploaded to the S3 bucket. Error:', 'external-files-from-aws-s3' ) . ' <code>' . esc_html( $e->getMessage() ) . '</code>', $target, 'error' );

			// do nothing more.
			return false;
		} finally {
			// close the stream, if the SDK has not done it already.
			if ( is_resource( $handle ) ) {
				fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			}
		}

		// bail if result is not the expected type.
		if ( ! $result instanceof Result ) {
			return false;
		}

		// bail if the file is not public available (not necessary for private files).
		if ( empty( $credentials['private'] ) && ! $s3_obj->is_file_public_available( $key, $s3_client ) ) {
			// log this event.
			Log::get_instance()->create( __( 'File would not be public available on your S3 bucket. Check the settings in the bucket to use the export of files.', 'external-files-from-aws-s3' ), $target, 'error' );

			// return false to prevent to save this file as external file.
			return false;
		}

		// invalidate the cache for an overwritten file.
		if ( $file_exists ) {
			$s3_obj->invalidate_cache( array( $key ) );
		}

		// save the used key.
		update_post_meta( $attachment_id, 'efml_s3_key', $key );

		// return the resulting public name of this file.
		return $s3_obj->get_public_url_of_file( $key, $s3_obj->get_fields() );
	}

	/**
	 * Delete an exported file.
	 *
	 * @param string              $url           The URL to delete.
	 * @param array<string,mixed> $credentials   The credentials to use.
	 * @param int                 $attachment_id The attachment ID.
	 *
	 * @return bool
	 */
	public function delete_exported_file( string $url, array $credentials, int $attachment_id ): bool {
		// get our own service object.
		$s3_obj = $this->get_directory_listing_object();

		// bail if no "Platform_Base" object could be loaded.
		if ( ! $s3_obj instanceof Platform_Base ) {
			return false;
		}

		// set the credentials.
		$s3_obj->set_fields( isset( $credentials['fields'] ) ? $credentials['fields'] : array() );

		// bail if the given URL is not an S3 URL.
		if ( ! str_starts_with( $url, $s3_obj->get_directory() ) ) {
			// log this event.
			Log::get_instance()->create( __( 'Given path is not a S3-URL.', 'external-files-from-aws-s3' ), $url, 'error' );

			// do nothing more.
			return false;
		}

		// get the key for the file.
		$key = get_post_meta( $attachment_id, 'efml_s3_key', true );

		// bail if key is not set.
		if ( empty( $key ) ) {
			return false;
		}

		// get the S3Client.
		$s3_client = $s3_obj->get_s3_client();

		// delete the file.
		try {
			$s3_client->deleteObject(
				array(
					'Bucket' => $s3_obj->get_bucket_name(),
					'Key'    => $key,
				)
			);
		} catch ( AwsException | Exception $e ) {
			// add log entry.
			Log::get_instance()->create( __( 'File could not be deleted! Error:', 'external-files-from-aws-s3' ) . ' <code>' . $e->getMessage() . '</code>', '', 'error' );

			// do nothing more.
			return false;
		}

		// invalidate the cache for the deleted file.
		if ( $s3_obj->is_cache_invalidation_enabled() ) {
			$s3_obj->invalidate_cache( array( (string) $key ) );
		}

		// return true as file has been deleted.
		return true;
	}

	/**
	 * Return whether a file with the given target path already exists in the bucket.
	 *
	 * Used by "External Files in Media Library" to get a unique filename before a new file is exported,
	 * so an existing file in the bucket is never overwritten.
	 *
	 * @param string              $target The target path of the file.
	 * @param array<string,mixed> $credentials The credentials to use.
	 *
	 * @return bool
	 */
	public function file_exists_on_target( string $target, array $credentials ): bool {
		// get our own service object.
		$s3_obj = $this->get_directory_listing_object();

		// bail if no "Platform_Base" object could be loaded.
		if ( ! $s3_obj instanceof Platform_Base ) {
			return false;
		}

		// set the credentials.
		$s3_obj->set_fields( isset( $credentials['fields'] ) ? $credentials['fields'] : array() );

		// bail if the given URL is not an S3 URL.
		if ( ! str_starts_with( $target, $s3_obj->get_directory() ) ) {
			return false;
		}

		// get the key for this file.
		$key = $this->get_key_for_filename( $this->get_folder_from_target( $target, $credentials ) . basename( $target ), $credentials, $s3_obj );

		// return whether the object exists in the bucket.
		// (errors other than "not found" result in an exception, which is handled by the caller).
		return $s3_obj->get_s3_client()->doesObjectExistV2( $s3_obj->get_bucket_name(), $key );
	}

	/**
	 * Return whether this service supports folders for exported files.
	 *
	 * S3 uses the folders as part of the key of the file, so no folders must be created.
	 *
	 * @return bool
	 */
	public function supports_folders(): bool {
		return true;
	}

	/**
	 * Return the folder of the given target below the directory of the external source, with trailing slash.
	 *
	 * Returns an empty string if the target is located directly in the directory of the external source
	 * or not located in it at all.
	 *
	 * @param string              $target The target path of the file.
	 * @param array<string,mixed> $credentials The credentials of the external source.
	 *
	 * @return string
	 */
	protected function get_folder_from_target( string $target, array $credentials ): string {
		// get the directory from the credentials.
		$directory = isset( $credentials['directory'] ) && is_string( $credentials['directory'] ) ? trailingslashit( $credentials['directory'] ) : '';

		// bail if no directory is set or the target is not located in it.
		if ( in_array( $directory, array( '', '/' ), true ) || ! str_starts_with( $target, $directory ) ) {
			return '';
		}

		// get the folder of the target below the directory.
		$folder = dirname( substr( $target, strlen( $directory ) ) );

		// bail if the target is located directly in the directory.
		if ( '.' === $folder || '' === $folder ) {
			return '';
		}

		// bail on relative folders.
		if ( in_array( '..', explode( '/', $folder ), true ) ) {
			return '';
		}

		// return the folder.
		return trailingslashit( $folder );
	}

	/**
	 * Return the key in the bucket for a given filename.
	 *
	 * The key contains the directory of the external source and the filename.
	 *
	 * @param string              $filename The filename.
	 * @param array<string,mixed> $credentials The credentials of the external source.
	 * @param Platform_Base       $s3_obj The platform object.
	 *
	 * @return string
	 */
	protected function get_key_for_filename( string $filename, array $credentials, Platform_Base $s3_obj ): string {
		// get the directory from the credentials.
		$directory = isset( $credentials['directory'] ) && is_string( $credentials['directory'] ) ? $credentials['directory'] : '';

		// prepend the directory (without the URL mark of the bucket) to the filename.
		return str_replace( $s3_obj->get_directory(), '', $directory ) . $filename;
	}

	/**
	 * Return the parameters for the upload of a file, e.g., its content type.
	 *
	 * @param int           $attachment_id The attachment ID.
	 * @param string        $file_path The absolute path to the local file.
	 * @param string        $key The key of the file in the bucket.
	 * @param Platform_Base $s3_obj The platform object.
	 *
	 * @return array<string,mixed>
	 */
	protected function get_upload_params( int $attachment_id, string $file_path, string $key, Platform_Base $s3_obj ): array {
		// get the content type from the attachment.
		$content_type = (string) get_post_mime_type( $attachment_id );

		// use the file extension as fallback.
		if ( empty( $content_type ) ) {
			$file_type    = wp_check_filetype( basename( $file_path ) );
			$content_type = is_string( $file_type['type'] ) ? $file_type['type'] : '';
		}

		// set the params.
		$params = array();
		if ( ! empty( $content_type ) ) {
			$params['ContentType'] = $content_type;
		}

		/**
		 * Filter the parameters for the upload of a file to the S3 bucket.
		 *
		 * Could be used to add e.g. "CacheControl" or "ContentDisposition".
		 *
		 * @since 1.3.0 Available since 1.3.0.
		 * @param array<string,mixed> $params The parameters.
		 * @param int $attachment_id The attachment ID.
		 * @param string $key The key of the file in the bucket.
		 * @param Platform_Base $s3_obj The platform object.
		 */
		return apply_filters( 'efmlawss3_upload_params', $params, $attachment_id, $key, $s3_obj );
	}

	/**
	 * Return the corresponding "Platform_Base" object.
	 *
	 * @return Platform_Base|false
	 */
	protected function get_directory_listing_object(): Platform_Base|false {
		return false;
	}

	/**
	 * Return whether this service could save exported files privately.
	 *
	 * Private files are delivered via the proxy of External Files in Media Library, from its cache or via a
	 * redirect to a pre-signed URL.
	 *
	 * @since 2.0.0 Available since 2.0.0.
	 *
	 * @return bool
	 */
	public function supports_private_files(): bool {
		return true;
	}
}
