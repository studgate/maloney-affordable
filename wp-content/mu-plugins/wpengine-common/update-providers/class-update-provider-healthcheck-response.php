<?php
/**
 * Data transfer object for the healthcheck response.
 *
 * @package wpengine/common-mu-plugin
 */

namespace wpe\plugin\update_providers;

/**
 * Data transfer object for the healthcheck response.
 */
class Update_Provider_Healthcheck_Response {
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
	 * The domain name of the API provider to use in display.
	 *
	 * @var string
	 */
	public $display_domain;

	/**
	 * This timestamp is explicit about being in seconds because we interact with JavaScript
	 * which uses millisecond timestamps.
	 *
	 * @var int The timestamp of the last time the connection to the update provider was checked.
	 */
	public $last_connection_check_timestamp_in_seconds = 0;

	/**
	 * This timestamp is explicit about being in seconds because we interact with JavaScript
	 * which uses millisecond timestamps.
	 *
	 * @var int The timestamp of the next time the connection to the update provider will be checked.
	 */
	public $next_connection_check_timestamp_in_seconds = 0;

	/**
	 * Whether the update provider was reachable when last checked.
	 *
	 * @var bool
	 */
	public $last_connection_check_result = false;

	/**
	 * Static method to create a response object from an update provider object.
	 *
	 * @param Update_Provider $provider The update provider object to create from.
	 *
	 * @return self
	 */
	public static function from_update_provider( Update_Provider $provider ) {
		$response                 = new self();
		$response->name           = $provider->name;
		$response->display_name   = $provider->display_name;
		$response->display_domain = $provider->display_domain;
		$response->last_connection_check_timestamp_in_seconds = $provider->last_connection_check_timestamp_in_seconds;
		$response->next_connection_check_timestamp_in_seconds = $provider->next_cache_expiry_timestamp();
		$response->last_connection_check_result               = $provider->last_connection_check_result;

		return $response;
	}
}
