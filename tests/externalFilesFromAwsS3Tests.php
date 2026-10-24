<?php
/**
 * File to handle the main object for each test class.
 *
 * @package external-files-from-aws-s3
 */

namespace ExternalFilesFromAwsS3\Tests;

use Aws\CommandInterface;
use Aws\Result;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use ExternalFilesFromAwsS3\Platform_Base;
use ExternalFilesFromAwsS3\Platforms\AwsS3;
use ExternalFilesFromAwsS3\Platforms\BackblazeS3;
use ExternalFilesFromAwsS3\Platforms\CloudflareR2;
use ExternalFilesFromAwsS3\Platforms\DigitalOceanSpaces;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use ReflectionClass;
use WP_Error;
use WP_HTTP_Requests_Response;
use WP_UnitTestCase;

/**
 * Object to handle the preparations for each test class.
 */
abstract class externalFilesFromAwsS3Tests extends WP_UnitTestCase {
	/**
	 * List of the supported platforms.
	 *
	 * @var array<int,class-string<Platform_Base>>
	 */
	protected static array $platforms = array(
		AwsS3::class,
		BackblazeS3::class,
		CloudflareR2::class,
		DigitalOceanSpaces::class,
	);

	/**
	 * List of HTTP responses the actual test expects, with the URL as index.
	 *
	 * @var array<string,array<string,mixed>>
	 */
	private static array $http_responses = array();

	/**
	 * List of HTTP requests during the actual test.
	 *
	 * @var array<int,array<string,string>>
	 */
	private static array $http_requests = array();

	/**
	 * List of S3 commands, which has been sent during the actual test.
	 *
	 * @var array<int,CommandInterface>
	 */
	private static array $s3_commands = array();

	/**
	 * List of configurations, which has been used for S3 clients during the actual test.
	 *
	 * @var array<int,array<string,mixed>>
	 */
	private static array $s3_configurations = array();

	/**
	 * List of the original platform objects, which have been replaced by a mock during the actual test.
	 *
	 * @var array<string,Platform_Base>
	 */
	private array $replaced_platforms = array();

	/**
	 * Prepare the test environment for each test class.
	 *
	 * @return void
	 */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();

		// prepare to load just one time.
		if ( ! did_action( 'efmlawss3_test_preparation_loaded' ) ) {
			// enable error reporting.
			error_reporting( E_ALL );

			// enable the extended log mode.
			update_option( 'eml_log_mode', 2 );

			// initialize the main plugin.
			\ExternalFilesInMediaLibrary\Plugin\Install::get_instance()->activation();

			// initialize this plugin (adds the capabilities for the platforms).
			\ExternalFilesFromAwsS3\Plugin\Init::get_instance()->activation();

			// start with an empty log, as its table is kept between test runs.
			\ExternalFilesInMediaLibrary\Plugin\Log::get_instance()->truncate_log();

			// run initialization.
			do_action( 'init' );

			// prevent any HTTP requests to external sources.
			add_filter( 'pre_http_request', array( self::class, 'filter_http_requests' ), 10, 3 );

			// prevent sending of emails.
			add_filter( 'wp_mail', array( self::class, 'filter_wp_mail' ) );

			// mark as loaded.
			do_action( 'efmlawss3_test_preparation_loaded' );
		}
	}

	/**
	 * Prepare each test.
	 *
	 * The platform objects are singletons, so we reset their state before each test.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		// reset the platform objects.
		foreach ( self::$platforms as $platform_class_name ) {
			$this->reset_platform( $platform_class_name::get_instance() );
		}

		// reset the lists for the mocks.
		self::$http_responses    = array();
		self::$http_requests     = array();
		self::$s3_commands       = array();
		self::$s3_configurations = array();
	}

	/**
	 * Cleanup after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		// restore the original platform objects.
		foreach ( $this->replaced_platforms as $platform_class_name => $original_object ) {
			$this->set_platform_instance( $platform_class_name, $original_object );
		}
		$this->replaced_platforms = array();

		// logout.
		$this->do_logout();

		parent::tear_down();
	}

	/**
	 * Filter any HTTP request during the tests to prevent any external communication.
	 *
	 * Tests can set the response for a specific URL with add_http_response().
	 *
	 * @param false|array|WP_Error $response    The return value.
	 * @param array                $parsed_args The used parameters.
	 * @param string               $url         The requested URL.
	 *
	 * @return false|array|WP_Error
	 */
	public static function filter_http_requests( false|array|WP_Error $response, array $parsed_args, string $url ): false|array|WP_Error {
		// let requests through, which a test has already answered.
		if ( false !== $response ) {
			return $response;
		}

		// log the request.
		self::$http_requests[] = array(
			'url'    => $url,
			'method' => isset( $parsed_args['method'] ) ? (string) $parsed_args['method'] : 'GET',
		);

		// show error if no response is configured for this URL, as we do not want any external URL connection during PHP Unit Tests.
		if ( ! isset( self::$http_responses[ $url ] ) ) {
			self::fail( 'Trying an external URL connection, which should not happen during PHP Unit Tests. Used URL: ' . $url );
		}

		// return the configured response.
		$configured_response = self::$http_responses[ $url ];
		return self::get_http_response( $url, $configured_response['code'], $configured_response['headers'], $configured_response['body'] );
	}

	/**
	 * Filter any email communication from WordPress.
	 *
	 * @param array $args The used arguments.
	 *
	 * @return array
	 */
	public static function filter_wp_mail( array $args ): array {
		$args['to'] = 'info@example.com';
		return $args;
	}

	/**
	 * Set the response for an HTTP request on the given URL for the actual test.
	 *
	 * @param string               $url         The URL.
	 * @param int                  $status_code The HTTP status code.
	 * @param array<string,string> $headers     The response headers.
	 * @param string               $body        The response body.
	 *
	 * @return void
	 */
	protected function add_http_response( string $url, int $status_code, array $headers = array(), string $body = '' ): void {
		self::$http_responses[ $url ] = array(
			'code'    => $status_code,
			'headers' => $headers,
			'body'    => $body,
		);
	}

	/**
	 * Return the list of HTTP requests during the actual test.
	 *
	 * @return array<int,array<string,string>>
	 */
	protected function get_http_requests(): array {
		return self::$http_requests;
	}

	/**
	 * Return a response array, like WordPress returns it for an HTTP request.
	 *
	 * @param string $url         The requested URL.
	 * @param int    $status_code The HTTP status code.
	 * @param array  $headers     The response headers.
	 * @param string $body        The response body.
	 *
	 * @return array
	 */
	protected static function get_http_response( string $url, int $status_code, array $headers = array(), string $body = '' ): array {
		// create the response object.
		$requests_response              = new \WpOrg\Requests\Response();
		$requests_response->status_code = $status_code;
		$requests_response->url         = $url;
		$requests_response->body        = $body;
		$requests_response->headers     = new \WpOrg\Requests\Response\Headers( $headers );
		$requests_response->success     = $status_code < 400;

		// return the WordPress response.
		$http_response             = new WP_HTTP_Requests_Response( $requests_response, null );
		$response                  = $http_response->to_array();
		$response['http_response'] = $http_response;
		return $response;
	}

	/**
	 * Replace the singleton of the given platform with a mock, which uses an S3 client without any
	 * external communication.
	 *
	 * The responses are set per S3 command name (e.g. "ListObjectsV2"). Each response can be:
	 * - an array with the result data.
	 * - a Result object.
	 * - an S3Exception (see get_s3_exception()).
	 * - a callable, which gets the command and returns one of the above.
	 *
	 * Commands without a configured response fail the test.
	 *
	 * @param class-string<Platform_Base> $platform_class_name The class name of the platform.
	 * @param array<string,mixed>         $responses           List of responses per S3 command.
	 *
	 * @return Platform_Base
	 */
	protected function use_s3_mock( string $platform_class_name, array $responses ): Platform_Base {
		// create the mock, which uses the original methods except for the creation of the S3 client.
		$mock = $this->getMockBuilder( $platform_class_name )
			->disableOriginalConstructor()
			->onlyMethods( array( 'get_the_client' ) )
			->getMock();

		// create the S3 client with a handler, which answers the commands locally.
		$test = $this;
		$mock->method( 'get_the_client' )->willReturnCallback(
			static function ( array $configuration ) use ( $responses, $test ): S3Client {
				// save the configuration for checks in the tests.
				self::$s3_configurations[] = $configuration;

				// set the handler for the commands.
				$configuration['handler'] = static function ( CommandInterface $command, ?RequestInterface $request = null ) use ( $responses, $test ) {
					// save the command for checks in the tests.
					self::$s3_commands[] = $command;

					// fail if no response is configured for this command.
					if ( ! array_key_exists( $command->getName(), $responses ) ) {
						$test->fail( 'Unexpected S3 command during PHP Unit Tests: ' . $command->getName() );
					}

					// get the response.
					$response = $responses[ $command->getName() ];
					if ( is_callable( $response ) ) {
						$response = $response( $command );
					}

					// return the exception as rejected promise.
					if ( $response instanceof S3Exception ) {
						return Create::rejectionFor( $response );
					}

					// return the result.
					return Create::promiseFor( $response instanceof Result ? $response : new Result( (array) $response ) );
				};

				// return the S3 client.
				return new S3Client( $configuration );
			}
		);

		// save the original object to restore it after the test.
		if ( ! isset( $this->replaced_platforms[ $platform_class_name ] ) ) {
			$this->replaced_platforms[ $platform_class_name ] = $platform_class_name::get_instance();
		}

		// use the mock as singleton.
		$this->set_platform_instance( $platform_class_name, $mock );

		// return the mock.
		return $mock;
	}

	/**
	 * Return an S3 exception for the given command.
	 *
	 * @param string $command_name The name of the command.
	 * @param int    $status_code  The HTTP status code.
	 * @param string $error_code   The S3 error code.
	 *
	 * @return S3Exception
	 */
	protected function get_s3_exception( string $command_name, int $status_code = 403, string $error_code = 'AccessDenied' ): S3Exception {
		return new S3Exception(
			'Error executing "' . $command_name . '": ' . $error_code,
			new \Aws\Command( $command_name ),
			array(
				'code'     => $error_code,
				'response' => new Response( $status_code ),
			)
		);
	}

	/**
	 * Return the list of S3 commands, which has been sent during the actual test.
	 *
	 * @return array<int,CommandInterface>
	 */
	protected function get_s3_commands(): array {
		return self::$s3_commands;
	}

	/**
	 * Return the names of the S3 commands, which has been sent during the actual test.
	 *
	 * @return array<int,string>
	 */
	protected function get_s3_command_names(): array {
		return array_map(
			static function ( CommandInterface $command ): string {
				return $command->getName();
			},
			self::$s3_commands
		);
	}

	/**
	 * Return the configurations, which has been used for S3 clients during the actual test.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	protected function get_s3_configurations(): array {
		return self::$s3_configurations;
	}

	/**
	 * Return a result for the S3 command "GetObjectAcl".
	 *
	 * @param bool $public True if the file should be readable by all users.
	 *
	 * @return array<string,mixed>
	 */
	protected function get_acl_result( bool $public ): array {
		$grants = array(
			array(
				'Grantee'    => array(
					'Type' => 'CanonicalUser',
					'ID'   => 'owner',
				),
				'Permission' => 'FULL_CONTROL',
			),
		);
		if ( $public ) {
			$grants[] = array(
				'Grantee'    => array(
					'Type' => 'Group',
					'URI'  => 'http://acs.amazonaws.com/groups/global/AllUsers',
				),
				'Permission' => 'READ',
			);
		}
		return array( 'Grants' => $grants );
	}

	/**
	 * Set the values of the fields of the given platform.
	 *
	 * @param Platform_Base       $platform The platform object.
	 * @param array<string,mixed> $values   The values with the field name as index.
	 *
	 * @return array<string,mixed>
	 */
	protected function set_platform_fields( Platform_Base $platform, array $values ): array {
		// get the fields with their default configuration.
		$fields = $platform->get_fields();

		// set the values.
		foreach ( $values as $name => $value ) {
			if ( ! isset( $fields[ $name ] ) ) {
				$fields[ $name ] = array( 'name' => $name );
			}
			$fields[ $name ]['value'] = $value;
		}

		// save them on the object.
		$platform->set_fields( $fields );

		// return the fields.
		return $fields;
	}

	/**
	 * Reset the state of the given platform object.
	 *
	 * @param Platform_Base $platform The platform object.
	 *
	 * @return void
	 */
	protected function reset_platform( Platform_Base $platform ): void {
		$platform->set_fields( array() );
		$platform->set_directory( '' );
		$this->set_property( $platform, 'errors', array() );
	}

	/**
	 * Set the singleton instance of the given platform.
	 *
	 * @param class-string<Platform_Base> $platform_class_name The class name of the platform.
	 * @param Platform_Base               $instance            The object to use.
	 *
	 * @return void
	 */
	private function set_platform_instance( string $platform_class_name, Platform_Base $instance ): void {
		$reflection = new ReflectionClass( $platform_class_name );
		$reflection->setStaticPropertyValue( 'instance', $instance );
	}

	/**
	 * Set the value of a (not public) property of the given object.
	 *
	 * @param object $obj      The object.
	 * @param string $property The name of the property.
	 * @param mixed  $value    The value.
	 *
	 * @return void
	 */
	protected function set_property( object $obj, string $property, mixed $value ): void {
		$reflection = new ReflectionClass( $obj );
		while ( ! $reflection->hasProperty( $property ) && $reflection->getParentClass() ) {
			$reflection = $reflection->getParentClass();
		}
		$reflection->getProperty( $property )->setValue( $obj, $value );
	}

	/**
	 * Call a not public method of the given object.
	 *
	 * @param object            $obj    The object.
	 * @param string            $method The name of the method.
	 * @param array<int,mixed>  $args   The arguments.
	 *
	 * @return mixed
	 */
	protected function call_method( object $obj, string $method, array $args = array() ): mixed {
		return ( new \ReflectionMethod( $obj, $method ) )->invokeArgs( $obj, $args );
	}

	/**
	 * Return the error messages of the given platform object.
	 *
	 * @param Platform_Base $platform The platform object.
	 *
	 * @return array<int,string>
	 */
	protected function get_error_messages( Platform_Base $platform ): array {
		$messages = array();
		foreach ( $platform->get_errors() as $error ) {
			$messages = array_merge( $messages, $error->get_error_messages() );
		}
		return $messages;
	}

	/**
	 * Return the log entries with the given URL, the newest first.
	 *
	 * @param string $url The URL.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	protected function get_log_entries( string $url = '' ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'eml_logs';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT `url`, `log`, `state` FROM `$table` WHERE `url` = %s ORDER BY `id` DESC", $url ), ARRAY_A );
	}

	/**
	 * Login in this session as admin.
	 *
	 * @return void
	 */
	protected function do_login(): void {
		$user_id    = 1;
		$user_login = 'admin';
		wp_set_current_user( $user_id, $user_login );
		wp_set_auth_cookie( $user_id );
		do_action( 'wp_login', $user_login );
	}

	/**
	 * Logout from this session.
	 *
	 * @return void
	 */
	protected function do_logout(): void {
		wp_set_current_user( 0 );
	}

	/**
	 * Create an attachment with a local file and return its ID.
	 *
	 * @param string $file_name The name of the file in tests/Data/.
	 *
	 * @return int
	 */
	protected function create_local_attachment( string $file_name = 'example.jpg' ): int {
		return (int) self::factory()->attachment->create_upload_object( UNIT_TESTS_DATA_PLUGIN_DIR . $file_name );
	}
}
