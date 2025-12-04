<?php
/**
 * Abstract Update Provider class.
 *
 * @package wpengine/common-mu-plugin
 */

namespace wpe\plugin\update_providers;

use wpe\plugin\utils\WPE_Direct_Http;

/**
 * This abstract class provides the base functionality for update providers and defines the
 * properties and methods that each update provider must implement.
 *
 * An update provider represents a source of plugin, theme and core updates that can be
 * used instead of the default WordPress repository.
 */
abstract class Update_Provider {
	/**
	 * The time in seconds that the API reachable check result is cached for.
	 *
	 * We will filter this, and there is also a maximum and minimum value that
	 * can be set by the filter.
	 */
	const API_REACHABLE_CACHE_TIME_IN_SECONDS     = 15 * MINUTE_IN_SECONDS;
	const MAX_API_REACHABLE_CACHE_TIME_IN_SECONDS = 60 * MINUTE_IN_SECONDS;
	const MIN_API_REACHABLE_CACHE_TIME_IN_SECONDS = 5 * MINUTE_IN_SECONDS;

	/**
	 * The prefix for the transient key used to cache the API reachable check result.
	 */
	const API_REACHABLE_TRANSIENT = 'wpe_update_provider_api_reachable';

	/**
	 * Keys to use for the cached result.
	 */
	const CACHED_STATUS_KEY    = 'status';
	const CACHED_TIMESTAMP_KEY = 'last_checked_timestamp';

	/**
	 * The internal name of the update provider.
	 *
	 * @var string
	 */
	public $name;

	/**
	 * The human-readable name of the update provider.
	 *
	 * @var string
	 */
	public $display_name;

	/**
	 * The URL of the API to check for this provider.
	 *
	 * @var string
	 */
	public $api_url;

	/**
	 * The domain name of the API provider to use in display.
	 *
	 * @var string
	 */
	public $display_domain;

	/**
	 * The timeout for HTTP requests.
	 *
	 * This is a variable/property so that it can be overridden in child classes.
	 *
	 * @var int
	 */
	protected $api_check_http_timeout_seconds = 5;

	/**
	 * The user agent for HTTP requests.
	 *
	 * This is a variable/property so that it can be overridden in child classes.
	 *
	 * @var string
	 */
	protected $api_check_http_user_agent = '';

	/**
	 * This timestamp is explicit about being in seconds because we interact with JavaScript
	 * which uses millisecond timestamps.
	 *
	 * @var int The timestamp of the last time the connection to the update provider was checked.
	 */
	public $last_connection_check_timestamp_in_seconds = 0;

	/**
	 * Whether the update provider was reachable when last checked.
	 *
	 * @var bool
	 */
	public $last_connection_check_result = false;

	/**
	 * Constructor
	 *
	 * @return void
	 */
	public function __construct() {
		$this->load_cached_api_status();
	}

	/**
	 * Get the transient key for the API reachable check result.
	 *
	 * This is a function to allow the constant to be modified on a
	 * provider-specific basis if required.
	 *
	 * @return string
	 */
	public function api_reachable_transient_key() {
		return self::API_REACHABLE_TRANSIENT;
	}

	/**
	 * Gets the cached API reachable check result for this provider.
	 *
	 * @see set_api_reachable_cached_status() The method for setting the cached result.
	 *
	 * @return array
	 */
	public function get_api_reachable_cached_status() {
		$transient_key = $this->api_reachable_transient_key();
		$result        = get_transient( $transient_key );

		if ( false === $result || ! isset( $result[ $this->name ] ) ) {
			return array();
		}

		return $result[ $this->name ];
	}

	/**
	 * Load the cached API reachable check result into the object.
	 *
	 * @return bool Whether the cached status was loaded.
	 */
	public function load_cached_api_status() {
		$result = $this->get_api_reachable_cached_status();

		if ( isset( $result[ self::CACHED_TIMESTAMP_KEY ] ) && isset( $result[ self::CACHED_STATUS_KEY ] ) ) {
			$this->last_connection_check_result               = $result[ self::CACHED_STATUS_KEY ];
			$this->last_connection_check_timestamp_in_seconds = $result[ self::CACHED_TIMESTAMP_KEY ];
			return true;
		}

		return false;
	}

	/**
	 * Set the cached API reachable check result.
	 *
	 * The cache is an array with provider names as keys like:
	 *
	 * [
	 *   <provider_name> =>
	 *       [
	 *           'status' => <status>,
	 *           'last_checked_timestamp' => <timestamp>,
	 *       ]
	 * ]
	 *
	 * @see get_api_reachable_cached_status() The method for reading back the cached result.
	 *
	 * @param int  $last_checked_timestamp The timestamp of the last check.
	 * @param bool $status                 The status of the last check.
	 *
	 * @return bool
	 */
	public function set_cached_api_status( $last_checked_timestamp, $status ) {
		$transient_key = $this->api_reachable_transient_key();
		$statuses      = get_transient( $transient_key );
		$statuses      = is_array( $statuses ) ? $statuses : array();

		$value_to_cache = array(
			self::CACHED_TIMESTAMP_KEY => $last_checked_timestamp,
			self::CACHED_STATUS_KEY    => $status,
		);

		$statuses[ $this->name ] = $value_to_cache;

		return set_transient( $transient_key, $statuses, $this->get_api_reachable_cache_time() );
	}

	/**
	 * Gets the time in seconds that the API reachable check result is cached for.
	 *
	 * Applies the wpe_update_provider_api_reachable_cache_time filter, along with
	 * the maximum and minimum values.
	 *
	 * @return int
	 */
	public function get_api_reachable_cache_time() {
		$filtered_time = apply_filters( 'wpe_update_provider_api_reachable_cache_time', self::API_REACHABLE_CACHE_TIME_IN_SECONDS );

		if ( ! is_numeric( $filtered_time ) ) {
			return self::API_REACHABLE_CACHE_TIME_IN_SECONDS;
		}

		$filtered_time = max( self::MIN_API_REACHABLE_CACHE_TIME_IN_SECONDS, $filtered_time );
		return min( self::MAX_API_REACHABLE_CACHE_TIME_IN_SECONDS, $filtered_time );
	}

	/**
	 * Get the timestamp of the next cache expiry.
	 *
	 * @return int
	 */
	public function next_cache_expiry_timestamp() {
		return ( $this->last_connection_check_timestamp_in_seconds + $this->get_api_reachable_cache_time() );
	}

	/**
	 * Return the number of seconds until the cache expires.
	 *
	 * Will be negative if the cache has expired.
	 *
	 * @return int
	 */
	public function time_to_cache_expiry() {
		return ( $this->next_cache_expiry_timestamp() - time() );
	}

	/**
	 * Has the cached API reachable check result expired?
	 *
	 * @return bool
	 */
	public function has_cache_expired() {
		return $this->time_to_cache_expiry() <= 0;
	}

	/**
	 * If the api-reachable cache has expired, perform the API reachable check for the
	 * update provider and update the last connection check timestamp and result.
	 *
	 * Returns the api-reachable result.
	 *
	 * Caches the result in a transient.
	 *
	 * This could be called by the constructor, but we wanted to make
	 * updates explicit.
	 *
	 * @return boolean
	 */
	public function update_api_reachable() {
		if ( ! $this->has_cache_expired() ) {
			return $this->last_connection_check_result;
		}

		$this->last_connection_check_result               = $this->do_api_reachable_check();
		$this->last_connection_check_timestamp_in_seconds = time();

		$this->set_cached_api_status(
			$this->last_connection_check_timestamp_in_seconds,
			$this->last_connection_check_result
		);

		return $this->last_connection_check_result;
	}

	/**
	 * Perform the API reachable check for the update provider. Returns true if the API
	 * is reachable.
	 *
	 * If a different check is needed it should be implemented in the child class.
	 *
	 * @return boolean
	 */
	public function do_api_reachable_check() {
		$retries = 1;

		foreach ( range( 0, $retries ) as $i ) {
			$api_result = $this->make_api_reachable_check_request();

			if ( $api_result ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Builds the HTTP context resource for the API reachable check request.
	 *
	 * The options should be as documented for the HTTP context.
	 *
	 * See: https://www.php.net/manual/en/context.http.php
	 *
	 * @return resource
	 */
	public function build_http_context() {
		$http_options = array(
			'timeout' => (float) $this->api_check_http_timeout_seconds,
		);

		if ( ! empty( $this->api_check_http_user_agent ) ) {
			$http_options['user_agent'] = $this->api_check_http_user_agent;
		}

		return stream_context_create( array( 'http' => $http_options ) );
	}

	/**
	 * Make an API reachable check request for the update provider. Returns true if the API
	 * is reachable.
	 *
	 * This should NOT use wp_remote_get as that could be filtered to redirect the API
	 * elsewhere. All API tests should be direct.
	 *
	 * If a different check is needed it should be implemented in the child class.
	 *
	 * @return boolean
	 */
	public function make_api_reachable_check_request() {
		try {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$api_result = file_get_contents( $this->api_url, false, $this->build_http_context() );
		} catch ( \Exception $e ) {
			$api_result = false;
		}

		if ( false === $api_result ) {
			return false;
		}

		// Get response code.
		$response_code = 0;

		if ( ! is_array( $http_response_header ) ) {
			// Don't know why this would happen.
			return false;
		}

		// Check response headers for the HTTP response code. Example: "HTTP/1.1 200 OK".
		foreach ( $http_response_header as $header ) {
			if ( strpos( $header, 'HTTP/' ) === 0 ) {
				$response_code = (int) explode( ' ', $header )[1];
				break;
			}
		}

		return ( 200 === $response_code );
	}
}
